package cmd

import (
	"fmt"
	"strings"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/cli"
	"github.com/saturn-platform/saturn-cli/internal/config"
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
			return runLogin(args)
		},
	}

	return cmd
}

func runLogin(args []string) error {
	// Determine the Saturn instance URL
	var baseURL string

	if len(args) > 0 {
		baseURL = args[0]
	} else {
		// Use default instance URL from config
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

	// Normalize
	baseURL = strings.TrimRight(baseURL, "/")
	if !strings.HasPrefix(baseURL, "http://") && !strings.HasPrefix(baseURL, "https://") {
		baseURL = "https://" + baseURL
	}

	fmt.Printf("Authenticating with %s...\n", baseURL)

	result, err := cli.RunDeviceAuth(baseURL)
	if err != nil {
		return err
	}

	// Save token
	if err := cli.SaveAuthToConfig(baseURL, result.Token); err != nil {
		return fmt.Errorf("authenticated but failed to save token: %w", err)
	}

	fmt.Printf("\nAuthenticated as %s (team: %s)\n", result.UserName, result.TeamName)
	fmt.Println("You're all set! Try: saturn server list")

	return nil
}
