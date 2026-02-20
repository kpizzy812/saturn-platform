package cmd

import (
	"context"
	"fmt"
	"net/url"
	"os/exec"
	"runtime"
	"strings"
	"time"

	"github.com/spf13/cobra"
	"github.com/spf13/viper"

	"github.com/saturn-platform/saturn-cli/internal/config"
	"github.com/saturn-platform/saturn-cli/internal/service"
)

// NewLoginCommand creates the login command
func NewLoginCommand() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "login [url]",
		Short: "Authenticate with a Saturn instance via browser",
		Long: `Opens your browser to authorize the Saturn CLI.

If no URL is provided, uses the default instance URL from your config.
After authorization, the token is saved automatically.`,
		Example: `  saturn login
  saturn login https://saturn.ac
  saturn login https://dev.saturn.ac`,
		Args: cobra.MaximumNArgs(1),
		RunE: func(cmd *cobra.Command, args []string) error {
			return runLogin(cmd, args)
		},
	}

	cmd.Flags().String("name", "", "Context name for this instance (default: derived from hostname)")

	return cmd
}

func runLogin(_ *cobra.Command, args []string) error {
	// Determine the Saturn instance URL
	var baseURL string

	if len(args) > 0 {
		baseURL = args[0]
	} else {
		// Try to use default instance URL
		cfg, err := config.Load()
		if err == nil {
			instance, err := cfg.GetDefault()
			if err == nil {
				baseURL = instance.FQDN
			}
		}
	}

	if baseURL == "" {
		return fmt.Errorf("no Saturn URL provided. Usage: saturn login <url>\nExample: saturn login https://saturn.ac")
	}

	// Normalize URL
	baseURL = strings.TrimRight(baseURL, "/")
	if !strings.HasPrefix(baseURL, "http://") && !strings.HasPrefix(baseURL, "https://") {
		baseURL = "https://" + baseURL
	}

	// Validate URL
	parsedURL, err := url.Parse(baseURL)
	if err != nil || parsedURL.Host == "" {
		return fmt.Errorf("invalid URL: %s", baseURL)
	}

	fmt.Printf("Authenticating with %s...\n\n", baseURL)

	// Initialize device auth
	ctx := context.Background()
	authSvc := service.NewAuthService(baseURL)

	initResp, err := authSvc.InitDeviceAuth(ctx)
	if err != nil {
		return fmt.Errorf("failed to start authentication: %w", err)
	}

	// Display code and URL
	fmt.Printf("Your confirmation code: %s\n\n", initResp.Code)
	fmt.Printf("Open this URL to authorize:\n  %s\n\n", initResp.VerificationURL)

	// Try to open browser
	openBrowser(initResp.VerificationURL)

	fmt.Println("Waiting for authorization...")

	// Poll for token
	status, err := authSvc.PollForToken(ctx, initResp.Secret, 5*time.Second, 5*time.Minute)
	if err != nil {
		return fmt.Errorf("authorization failed: %w", err)
	}

	switch status.Status {
	case "approved":
		return saveLoginConfig(baseURL, parsedURL.Hostname(), status.Token, status.TeamName, status.UserName)
	case "denied":
		return fmt.Errorf("authorization was denied")
	case "expired":
		return fmt.Errorf("authorization request expired. Please try again")
	default:
		return fmt.Errorf("unexpected status: %s", status.Status)
	}
}

func saveLoginConfig(baseURL, hostname, token, teamName, userName string) error {
	cfg, err := config.Load()
	if err != nil {
		// Create new config
		cfg = config.New()
	}

	contextName := hostname
	instances := viper.Get("instances")
	if instances == nil {
		instances = []any{}
	}
	instanceList, ok := instances.([]any)
	if !ok {
		instanceList = []any{}
	}

	// Check if instance with this FQDN already exists
	found := false
	for _, inst := range instanceList {
		instMap, ok := inst.(map[string]any)
		if !ok {
			continue
		}
		if instMap["fqdn"] == baseURL {
			// Update existing instance token
			instMap["token"] = token
			found = true
			if name, ok := instMap["name"].(string); ok {
				contextName = name
			}
			break
		}
	}

	if !found {
		// Add new instance
		newInstance := config.Instance{
			Name:    contextName,
			FQDN:    baseURL,
			Token:   token,
			Default: len(instanceList) == 0,
		}

		// If this is the first instance, make it default
		if len(instanceList) == 0 {
			newInstance.Default = true
		}

		instanceList = append(instanceList, newInstance)
	}

	viper.Set("instances", instanceList)
	if err := viper.WriteConfig(); err != nil {
		// Try creating config file first
		if writeErr := cfg.Save(); writeErr != nil {
			return fmt.Errorf("failed to save config: %w", writeErr)
		}
		viper.Set("instances", instanceList)
		if err := viper.WriteConfig(); err != nil {
			return fmt.Errorf("failed to write config: %w", err)
		}
	}

	fmt.Printf("\nAuthenticated as %s (team: %s)\n", userName, teamName)
	fmt.Printf("Context '%s' saved.\n", contextName)
	fmt.Println("\nYou're all set! Try: saturn server list")

	return nil
}

func openBrowser(url string) {
	var cmd *exec.Cmd

	switch runtime.GOOS {
	case "darwin":
		cmd = exec.Command("open", url)
	case "linux":
		cmd = exec.Command("xdg-open", url)
	case "windows":
		cmd = exec.Command("rundll32", "url.dll,FileProtocolHandler", url)
	default:
		return
	}

	// Ignore errors — browser opening is best-effort (e.g. SSH sessions)
	_ = cmd.Start()
}
