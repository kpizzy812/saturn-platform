package cli

import (
	"context"
	"fmt"
	"net/url"
	"os/exec"
	"runtime"
	"strings"
	"time"

	"github.com/saturn-platform/saturn-cli/internal/config"
	"github.com/saturn-platform/saturn-cli/internal/service"
)

// DeviceAuthResult holds the result of a successful device auth flow
type DeviceAuthResult struct {
	Token    string
	TeamName string
	UserName string
}

// RunDeviceAuth performs the full browser-based device authorization flow.
// It inits a session, opens the browser, polls for approval, and returns the token.
func RunDeviceAuth(baseURL string) (*DeviceAuthResult, error) {
	// Normalize URL
	baseURL = strings.TrimRight(baseURL, "/")
	if !strings.HasPrefix(baseURL, "http://") && !strings.HasPrefix(baseURL, "https://") {
		baseURL = "https://" + baseURL
	}

	parsedURL, err := url.Parse(baseURL)
	if err != nil || parsedURL.Host == "" {
		return nil, fmt.Errorf("invalid URL: %s", baseURL)
	}

	ctx := context.Background()
	authSvc := service.NewAuthService(baseURL)

	initResp, err := authSvc.InitDeviceAuth(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to start authentication: %w", err)
	}

	fmt.Printf("\nConfirmation code: %s\n", initResp.Code)
	fmt.Printf("Open this URL to authorize:\n  %s\n\n", initResp.VerificationURL)

	openBrowser(initResp.VerificationURL)

	fmt.Println("Waiting for authorization...")

	status, err := authSvc.PollForToken(ctx, initResp.Secret, 5*time.Second, 5*time.Minute)
	if err != nil {
		return nil, fmt.Errorf("authorization failed: %w", err)
	}

	switch status.Status {
	case "approved":
		return &DeviceAuthResult{
			Token:    status.Token,
			TeamName: status.TeamName,
			UserName: status.UserName,
		}, nil
	case "denied":
		return nil, fmt.Errorf("authorization was denied")
	case "expired":
		return nil, fmt.Errorf("authorization request expired, please try again")
	default:
		return nil, fmt.Errorf("unexpected status: %s", status.Status)
	}
}

// SaveAuthToConfig saves the auth token to the config for the given instance.
// If an instance with the same FQDN exists, updates its token. Otherwise adds a new one.
func SaveAuthToConfig(baseURL, token string) error {
	cfg, err := config.Load()
	if err != nil {
		cfg = config.New()
	}

	// Try to find existing instance by FQDN
	found := false
	for i := range cfg.Instances {
		if cfg.Instances[i].FQDN == baseURL {
			cfg.Instances[i].Token = token
			found = true
			break
		}
	}

	if !found {
		parsedURL, _ := url.Parse(baseURL)
		hostname := "cloud"
		if parsedURL != nil && parsedURL.Host != "" {
			hostname = parsedURL.Hostname()
		}
		cfg.Instances = append(cfg.Instances, config.Instance{
			Name:    hostname,
			FQDN:    baseURL,
			Token:   token,
			Default: len(cfg.Instances) == 0,
		})
	}

	return cfg.Save()
}

func openBrowser(url string) {
	var cmd *exec.Cmd

	ctx := context.Background()
	switch runtime.GOOS {
	case "darwin":
		cmd = exec.CommandContext(ctx, "open", url)
	case "linux":
		cmd = exec.CommandContext(ctx, "xdg-open", url)
	case "windows":
		cmd = exec.CommandContext(ctx, "rundll32", "url.dll,FileProtocolHandler", url)
	default:
		return
	}

	// Best-effort — ignore errors (e.g. SSH sessions without display)
	_ = cmd.Start()
}
