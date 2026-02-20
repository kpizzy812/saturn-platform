package service

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"github.com/saturn-platform/saturn-cli/internal/api"
)

func TestDeploymentService_Deploy(t *testing.T) {
	tests := []struct {
		name         string
		uuid         string
		force        bool
		expectedPath string
		response     DeployResponse
	}{
		{
			name:         "deploy without force",
			uuid:         "res-123",
			force:        false,
			expectedPath: "/api/v1/deploy?uuid=res-123",
			response: DeployResponse{
				Deployments: []DeploymentInfo{
					{
						Message:        "Deployment started",
						ResourceUUID:   "res-123",
						DeploymentUUID: "dep-456",
					},
				},
			},
		},
		{
			name:         "deploy with force",
			uuid:         "res-789",
			force:        true,
			expectedPath: "/api/v1/deploy?uuid=res-789&force=true",
			response: DeployResponse{
				Deployments: []DeploymentInfo{
					{
						Message:        "Force deployment started",
						ResourceUUID:   "res-789",
						DeploymentUUID: "dep-999",
					},
				},
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				assert.Equal(t, tt.expectedPath, r.URL.Path+"?"+r.URL.RawQuery)
				assert.Equal(t, "GET", r.Method)

				w.Header().Set("Content-Type", "application/json")
				_ = json.NewEncoder(w).Encode(tt.response)
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.Deploy(context.Background(), tt.uuid, tt.force)
			require.NoError(t, err)
			assert.Len(t, result.Deployments, len(tt.response.Deployments))
			if len(result.Deployments) > 0 {
				assert.Equal(t, tt.response.Deployments[0].Message, result.Deployments[0].Message)
				assert.Equal(t, tt.response.Deployments[0].DeploymentUUID, result.Deployments[0].DeploymentUUID)
			}
		})
	}
}

func TestDeploymentService_ListByApplication(t *testing.T) {
	tests := []struct {
		name         string
		appUUID      string
		expectedPath string
		response     string
		wantErr      bool
		wantCount    int
	}{
		{
			name:         "successful list with deployments",
			appUUID:      "app-123",
			expectedPath: "/api/v1/deployments/applications/app-123",
			response: `{
				"count": 2,
				"deployments": [
					{
						"id": 1,
						"deployment_uuid": "dep-123",
						"application_id": 10,
						"application_name": "my-app",
						"server_name": "server-1",
						"status": "finished",
						"commit": "abc123"
					},
					{
						"id": 2,
						"deployment_uuid": "dep-456",
						"application_id": 10,
						"application_name": "my-app",
						"server_name": "server-1",
						"status": "in_progress",
						"commit": "def456"
					}
				]
			}`,
			wantErr:   false,
			wantCount: 2,
		},
		{
			name:         "empty list",
			appUUID:      "app-empty",
			expectedPath: "/api/v1/deployments/applications/app-empty",
			response:     `{"count": 0, "deployments": []}`,
			wantErr:      false,
			wantCount:    0,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				assert.Equal(t, tt.expectedPath, r.URL.Path)
				assert.Equal(t, "GET", r.Method)

				w.Header().Set("Content-Type", "application/json")
				_, _ = w.Write([]byte(tt.response))
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.ListByApplication(context.Background(), tt.appUUID)

			if tt.wantErr {
				require.Error(t, err)
			} else {
				require.NoError(t, err)
				assert.Len(t, result, tt.wantCount)
			}
		})
	}
}

func TestDeploymentService_GetLogsByApplication(t *testing.T) {
	tests := []struct {
		name              string
		appUUID           string
		lines             int
		deploymentsList   string
		deploymentDetails string
		expectedLogs      string
		wantErr           bool
		noDeployments     bool
		emptyLogs         bool
	}{
		{
			name:    "get logs successfully",
			appUUID: "app-123",
			lines:   0,
			deploymentsList: `{
				"count": 1,
				"deployments": [
					{
						"id": 1,
						"deployment_uuid": "dep-123",
						"application_name": "my-app",
						"status": "finished"
					}
				]
			}`,
			deploymentDetails: `{
				"id": 1,
				"deployment_uuid": "dep-123",
				"application_name": "my-app",
				"status": "finished",
				"logs": "Starting deployment...\nBuilding image...\nDeployment successful!"
			}`,
			expectedLogs: "Starting deployment...\nBuilding image...\nDeployment successful!",
			wantErr:      false,
		},
		{
			name:            "no deployments found",
			appUUID:         "app-empty",
			lines:           0,
			deploymentsList: `{"count": 0, "deployments": []}`,
			expectedLogs:    "No deployments found for this application",
			wantErr:         false,
			noDeployments:   true,
		},
		{
			name:    "empty logs",
			appUUID: "app-no-logs",
			lines:   0,
			deploymentsList: `{
				"count": 1,
				"deployments": [
					{
						"id": 1,
						"deployment_uuid": "dep-456",
						"application_name": "my-app",
						"status": "in_progress"
					}
				]
			}`,
			deploymentDetails: `{
				"id": 1,
				"deployment_uuid": "dep-456",
				"application_name": "my-app",
				"status": "in_progress",
				"logs": null
			}`,
			expectedLogs: "No logs available for deployment dep-456 (Status: in_progress)",
			wantErr:      false,
			emptyLogs:    true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				w.Header().Set("Content-Type", "application/json")

				// Handle list deployments request
				if r.URL.Path == "/api/v1/deployments/applications/"+tt.appUUID {
					_, _ = w.Write([]byte(tt.deploymentsList))
					return
				}

				// Handle get deployment details request
				if !tt.noDeployments && r.URL.Path == "/api/v1/deployments/dep-123" || r.URL.Path == "/api/v1/deployments/dep-456" {
					_, _ = w.Write([]byte(tt.deploymentDetails))
					return
				}

				w.WriteHeader(http.StatusNotFound)
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.GetLogsByApplication(context.Background(), tt.appUUID, tt.lines)

			if tt.wantErr {
				require.Error(t, err)
			} else {
				require.NoError(t, err)
				assert.Equal(t, tt.expectedLogs, result)
			}
		})
	}
}

func TestDeploymentService_ListByApplication_Error(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNotFound)
		_, _ = w.Write([]byte(`{"error": "application not found"}`))
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewDeploymentService(client)

	_, err := svc.ListByApplication(context.Background(), "nonexistent")
	require.Error(t, err)
}

func TestDeploymentService_GetLogsByApplication_Error(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
		_, _ = w.Write([]byte(`{"error": "internal server error"}`))
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewDeploymentService(client)

	_, err := svc.GetLogsByApplication(context.Background(), "app-123", 0)
	require.Error(t, err)
}

func TestDeploymentService_GetLogsByDeployment(t *testing.T) {
	tests := []struct {
		name             string
		deploymentUUID   string
		deploymentDetail string
		expectedLogs     string
		wantErr          bool
	}{
		{
			name:           "get logs successfully",
			deploymentUUID: "dep-123",
			deploymentDetail: `{
				"id": 1,
				"deployment_uuid": "dep-123",
				"application_name": "my-app",
				"status": "finished",
				"logs": "Starting deployment...\nBuilding image...\nDeployment successful!"
			}`,
			expectedLogs: "Starting deployment...\nBuilding image...\nDeployment successful!",
			wantErr:      false,
		},
		{
			name:           "empty logs",
			deploymentUUID: "dep-no-logs",
			deploymentDetail: `{
				"id": 1,
				"deployment_uuid": "dep-no-logs",
				"application_name": "my-app",
				"status": "in_progress",
				"logs": null
			}`,
			expectedLogs: "No logs available for deployment dep-no-logs (Status: in_progress)",
			wantErr:      false,
		},
		{
			name:           "logs with empty string",
			deploymentUUID: "dep-empty-string",
			deploymentDetail: `{
				"id": 1,
				"deployment_uuid": "dep-empty-string",
				"application_name": "my-app",
				"status": "queued",
				"logs": ""
			}`,
			expectedLogs: "No logs available for deployment dep-empty-string (Status: queued)",
			wantErr:      false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				w.Header().Set("Content-Type", "application/json")

				// Handle get deployment details request
				if r.URL.Path == "/api/v1/deployments/"+tt.deploymentUUID {
					_, _ = w.Write([]byte(tt.deploymentDetail))
					return
				}

				w.WriteHeader(http.StatusNotFound)
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.GetLogsByDeployment(context.Background(), tt.deploymentUUID)

			if tt.wantErr {
				require.Error(t, err)
			} else {
				require.NoError(t, err)
				assert.Equal(t, tt.expectedLogs, result)
			}
		})
	}
}

func TestDeploymentService_GetLogsByDeployment_Error(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNotFound)
		_, _ = w.Write([]byte(`{"error": "deployment not found"}`))
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewDeploymentService(client)

	_, err := svc.GetLogsByDeployment(context.Background(), "nonexistent")
	require.Error(t, err)
}

func TestDeploymentService_GetRollbackEvents(t *testing.T) {
	tests := []struct {
		name         string
		appUUID      string
		take         int
		expectedPath string
		response     string
		wantCount    int
		wantErr      bool
	}{
		{
			name:         "list all rollback events",
			appUUID:      "app-123",
			take:         0,
			expectedPath: "/api/v1/applications/app-123/rollback-events",
			response: `[
				{
					"id": 1,
					"application_id": 10,
					"trigger_reason": "manual",
					"trigger_type": "user",
					"status": "completed",
					"from_commit": "abc123",
					"to_commit": "def456"
				},
				{
					"id": 2,
					"application_id": 10,
					"trigger_reason": "auto",
					"trigger_type": "system",
					"status": "failed",
					"from_commit": "def456",
					"to_commit": "ghi789"
				}
			]`,
			wantCount: 2,
		},
		{
			name:         "list with take parameter",
			appUUID:      "app-456",
			take:         5,
			expectedPath: "/api/v1/applications/app-456/rollback-events?take=5",
			response:     `[]`,
			wantCount:    0,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				expectedURL := tt.expectedPath
				actualURL := r.URL.Path
				if r.URL.RawQuery != "" {
					actualURL += "?" + r.URL.RawQuery
				}
				assert.Equal(t, expectedURL, actualURL)
				assert.Equal(t, "GET", r.Method)

				w.Header().Set("Content-Type", "application/json")
				_, _ = w.Write([]byte(tt.response))
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.GetRollbackEvents(context.Background(), tt.appUUID, tt.take)

			if tt.wantErr {
				require.Error(t, err)
			} else {
				require.NoError(t, err)
				assert.Len(t, result, tt.wantCount)
			}
		})
	}
}

func TestDeploymentService_ExecuteRollback(t *testing.T) {
	tests := []struct {
		name           string
		appUUID        string
		deploymentUUID string
		expectedPath   string
		response       string
		wantErr        bool
	}{
		{
			name:           "successful rollback",
			appUUID:        "app-123",
			deploymentUUID: "dep-456",
			expectedPath:   "/api/v1/applications/app-123/rollback/dep-456",
			response:       `{"message": "Rollback started", "deployment_uuid": "dep-new-789", "rollback_event_id": 1}`,
		},
		{
			name:           "rollback to different deployment",
			appUUID:        "app-789",
			deploymentUUID: "dep-old",
			expectedPath:   "/api/v1/applications/app-789/rollback/dep-old",
			response:       `{"message": "Rollback initiated", "deployment_uuid": "dep-rollback-1"}`,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				assert.Equal(t, tt.expectedPath, r.URL.Path)
				assert.Equal(t, "POST", r.Method)

				w.Header().Set("Content-Type", "application/json")
				_, _ = w.Write([]byte(tt.response))
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.ExecuteRollback(context.Background(), tt.appUUID, tt.deploymentUUID)

			if tt.wantErr {
				require.Error(t, err)
			} else {
				require.NoError(t, err)
				assert.NotEmpty(t, result.Message)
			}
		})
	}
}

func TestDeploymentService_DeployByTag(t *testing.T) {
	tests := []struct {
		name         string
		tag          string
		force        bool
		expectedPath string
		response     DeployResponse
	}{
		{
			name:         "deploy by tag without force",
			tag:          "v1.0.0",
			force:        false,
			expectedPath: "/api/v1/deploy?tag=v1.0.0",
			response: DeployResponse{
				Deployments: []DeploymentInfo{
					{
						Message:        "Deployment started",
						ResourceUUID:   "res-123",
						DeploymentUUID: "dep-456",
					},
				},
			},
		},
		{
			name:         "deploy by tag with force",
			tag:          "production",
			force:        true,
			expectedPath: "/api/v1/deploy?tag=production&force=true",
			response: DeployResponse{
				Deployments: []DeploymentInfo{
					{
						Message:        "Force deployment started",
						ResourceUUID:   "res-789",
						DeploymentUUID: "dep-999",
					},
				},
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				actualURL := r.URL.Path + "?" + r.URL.RawQuery
				assert.Equal(t, tt.expectedPath, actualURL)
				assert.Equal(t, "GET", r.Method)

				w.Header().Set("Content-Type", "application/json")
				_ = json.NewEncoder(w).Encode(tt.response)
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.DeployByTag(context.Background(), tt.tag, tt.force)
			require.NoError(t, err)
			assert.Len(t, result.Deployments, len(tt.response.Deployments))
			if len(result.Deployments) > 0 {
				assert.Equal(t, tt.response.Deployments[0].Message, result.Deployments[0].Message)
				assert.Equal(t, tt.response.Deployments[0].DeploymentUUID, result.Deployments[0].DeploymentUUID)
			}
		})
	}
}

func TestDeploymentService_DeployByPR(t *testing.T) {
	tests := []struct {
		name         string
		uuid         string
		prID         int
		force        bool
		expectedPath string
		response     DeployResponse
	}{
		{
			name:         "deploy PR without force",
			uuid:         "app-123",
			prID:         42,
			force:        false,
			expectedPath: "/api/v1/deploy?uuid=app-123&pr=42",
			response: DeployResponse{
				Deployments: []DeploymentInfo{
					{
						Message:        "PR preview deployment started",
						ResourceUUID:   "app-123",
						DeploymentUUID: "dep-pr-42",
					},
				},
			},
		},
		{
			name:         "deploy PR with force",
			uuid:         "app-456",
			prID:         99,
			force:        true,
			expectedPath: "/api/v1/deploy?uuid=app-456&pr=99&force=true",
			response: DeployResponse{
				Deployments: []DeploymentInfo{
					{
						Message:        "Force PR preview deployment started",
						ResourceUUID:   "app-456",
						DeploymentUUID: "dep-pr-99",
					},
				},
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				actualURL := r.URL.Path + "?" + r.URL.RawQuery
				assert.Equal(t, tt.expectedPath, actualURL)
				assert.Equal(t, "GET", r.Method)

				w.Header().Set("Content-Type", "application/json")
				_ = json.NewEncoder(w).Encode(tt.response)
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.DeployByPR(context.Background(), tt.uuid, tt.prID, tt.force)
			require.NoError(t, err)
			assert.Len(t, result.Deployments, len(tt.response.Deployments))
			if len(result.Deployments) > 0 {
				assert.Equal(t, tt.response.Deployments[0].Message, result.Deployments[0].Message)
				assert.Equal(t, tt.response.Deployments[0].DeploymentUUID, result.Deployments[0].DeploymentUUID)
			}
		})
	}
}

func TestDeploymentService_GetDeploymentLogs(t *testing.T) {
	tests := []struct {
		name           string
		deploymentUUID string
		expectedPath   string
		response       string
		wantErr        bool
		wantStatus     string
		wantLogs       string
	}{
		{
			name:           "get logs successfully",
			deploymentUUID: "dep-123",
			expectedPath:   "/api/v1/deployments/dep-123/logs",
			response:       `{"deployment_uuid": "dep-123", "status": "finished", "logs": "Build started\nBuild completed"}`,
			wantStatus:     "finished",
			wantLogs:       "Build started\nBuild completed",
		},
		{
			name:           "get logs for in-progress deployment",
			deploymentUUID: "dep-456",
			expectedPath:   "/api/v1/deployments/dep-456/logs",
			response:       `{"deployment_uuid": "dep-456", "status": "in_progress", "logs": "Building..."}`,
			wantStatus:     "in_progress",
			wantLogs:       "Building...",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				assert.Equal(t, tt.expectedPath, r.URL.Path)
				assert.Equal(t, "GET", r.Method)

				w.Header().Set("Content-Type", "application/json")
				_, _ = w.Write([]byte(tt.response))
			}))
			defer server.Close()

			client := api.NewClient(server.URL, "test-token")
			svc := NewDeploymentService(client)

			result, err := svc.GetDeploymentLogs(context.Background(), tt.deploymentUUID)

			if tt.wantErr {
				require.Error(t, err)
			} else {
				require.NoError(t, err)
				assert.Equal(t, tt.wantStatus, result.Status)
				assert.Equal(t, tt.wantLogs, result.Logs)
			}
		})
	}
}

func TestDeploymentService_GetDeploymentLogs_Error(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNotFound)
		_, _ = w.Write([]byte(`{"error": "deployment not found"}`))
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewDeploymentService(client)

	_, err := svc.GetDeploymentLogs(context.Background(), "nonexistent")
	require.Error(t, err)
}
