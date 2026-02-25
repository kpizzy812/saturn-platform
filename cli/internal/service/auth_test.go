package service

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"
	"time"
)

func TestAuthService_InitDeviceAuth(t *testing.T) {
	tests := []struct {
		name       string
		response   string
		statusCode int
		wantErr    bool
		wantCode   string
	}{
		{
			name:       "successful init",
			response:   `{"code":"ABCD-EFGH","secret":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","verification_url":"https://saturn.ac/cli/auth?code=ABCD-EFGH","expires_in":300}`,
			statusCode: http.StatusOK,
			wantErr:    false,
			wantCode:   "ABCD-EFGH",
		},
		{
			name:       "rate limited",
			response:   `{"message":"Too Many Requests"}`,
			statusCode: http.StatusTooManyRequests,
			wantErr:    true,
		},
		{
			name:       "server error",
			response:   `{"message":"Internal Server Error"}`,
			statusCode: http.StatusInternalServerError,
			wantErr:    true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				if r.URL.Path != "/api/v1/cli/auth/init" {
					t.Errorf("Expected path /api/v1/cli/auth/init, got %s", r.URL.Path)
				}
				if r.Method != http.MethodPost {
					t.Errorf("Expected POST, got %s", r.Method)
				}
				w.WriteHeader(tt.statusCode)
				_, _ = w.Write([]byte(tt.response))
			}))
			defer server.Close()

			svc := NewAuthService(server.URL)
			resp, err := svc.InitDeviceAuth(context.Background())

			if (err != nil) != tt.wantErr {
				t.Errorf("InitDeviceAuth() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if !tt.wantErr && resp.Code != tt.wantCode {
				t.Errorf("InitDeviceAuth() code = %s, want %s", resp.Code, tt.wantCode)
			}
		})
	}
}

func TestAuthService_CheckAuthStatus(t *testing.T) {
	tests := []struct {
		name       string
		secret     string
		response   string
		statusCode int
		wantErr    bool
		wantStatus string
	}{
		{
			name:       "pending status",
			secret:     "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
			response:   `{"status":"pending"}`,
			statusCode: http.StatusOK,
			wantErr:    false,
			wantStatus: "pending",
		},
		{
			name:       "approved status with token",
			secret:     "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
			response:   `{"status":"approved","token":"1|abc123","team_name":"My Team","user_name":"John"}`,
			statusCode: http.StatusOK,
			wantErr:    false,
			wantStatus: "approved",
		},
		{
			name:       "expired status",
			secret:     "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
			response:   `{"status":"expired"}`,
			statusCode: http.StatusOK,
			wantErr:    false,
			wantStatus: "expired",
		},
		{
			name:       "session not found",
			secret:     "invalid",
			response:   `{"message":"Session not found."}`,
			statusCode: http.StatusNotFound,
			wantErr:    true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				if r.URL.Path != "/api/v1/cli/auth/check" {
					t.Errorf("Expected path /api/v1/cli/auth/check, got %s", r.URL.Path)
				}
				if r.Method != http.MethodGet {
					t.Errorf("Expected GET, got %s", r.Method)
				}
				secret := r.URL.Query().Get("secret")
				if secret != tt.secret {
					t.Errorf("Expected secret %s, got %s", tt.secret, secret)
				}
				w.WriteHeader(tt.statusCode)
				_, _ = w.Write([]byte(tt.response))
			}))
			defer server.Close()

			svc := NewAuthService(server.URL)
			status, err := svc.CheckAuthStatus(context.Background(), tt.secret)

			if (err != nil) != tt.wantErr {
				t.Errorf("CheckAuthStatus() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if !tt.wantErr && status.Status != tt.wantStatus {
				t.Errorf("CheckAuthStatus() status = %s, want %s", status.Status, tt.wantStatus)
			}
		})
	}
}

func TestAuthService_PollForToken_Approved(t *testing.T) {
	var callCount atomic.Int32

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		count := callCount.Add(1)
		w.WriteHeader(http.StatusOK)

		if count < 3 {
			_, _ = w.Write([]byte(`{"status":"pending"}`))
		} else {
			resp, _ := json.Marshal(map[string]string{
				"status":    "approved",
				"token":     "1|test-token",
				"team_name": "Test Team",
				"user_name": "Test User",
			})
			_, _ = w.Write(resp)
		}
	}))
	defer server.Close()

	svc := NewAuthService(server.URL)
	status, err := svc.PollForToken(context.Background(), "test-secret", 100*time.Millisecond, 5*time.Second)

	if err != nil {
		t.Fatalf("PollForToken() unexpected error: %v", err)
	}

	if status.Status != "approved" {
		t.Errorf("PollForToken() status = %s, want approved", status.Status)
	}
	if status.Token != "1|test-token" {
		t.Errorf("PollForToken() token = %s, want 1|test-token", status.Token)
	}
	if status.TeamName != "Test Team" {
		t.Errorf("PollForToken() team = %s, want Test Team", status.TeamName)
	}
}

func TestAuthService_PollForToken_Denied(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"status":"denied"}`))
	}))
	defer server.Close()

	svc := NewAuthService(server.URL)
	status, err := svc.PollForToken(context.Background(), "test-secret", 100*time.Millisecond, 5*time.Second)

	if err != nil {
		t.Fatalf("PollForToken() unexpected error: %v", err)
	}

	if status.Status != "denied" {
		t.Errorf("PollForToken() status = %s, want denied", status.Status)
	}
}

func TestAuthService_PollForToken_Timeout(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"status":"pending"}`))
	}))
	defer server.Close()

	svc := NewAuthService(server.URL)
	status, err := svc.PollForToken(context.Background(), "test-secret", 50*time.Millisecond, 200*time.Millisecond)

	if err != nil {
		t.Fatalf("PollForToken() unexpected error: %v", err)
	}

	if status.Status != "expired" {
		t.Errorf("PollForToken() status = %s, want expired", status.Status)
	}
}

func TestAuthService_PollForToken_ContextCancelled(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"status":"pending"}`))
	}))
	defer server.Close()

	ctx, cancel := context.WithCancel(context.Background())
	go func() {
		time.Sleep(150 * time.Millisecond)
		cancel()
	}()

	svc := NewAuthService(server.URL)
	_, err := svc.PollForToken(ctx, "test-secret", 50*time.Millisecond, 5*time.Second)

	if err == nil {
		t.Error("PollForToken() expected error on context cancellation")
	}
}
