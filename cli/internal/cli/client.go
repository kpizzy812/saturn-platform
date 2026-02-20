package cli

import (
	"fmt"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/api"
	"github.com/saturn-platform/saturn-cli/internal/config"
)

// GetAPIClient creates an API client from command flags or config.
// If the resolved instance has no token, it automatically triggers
// browser-based device auth so the user doesn't have to run "saturn login" first.
func GetAPIClient(cmd *cobra.Command) (*api.Client, error) {
	// Get flags
	token, _ := cmd.Flags().GetString("token")
	contextName, _ := cmd.Flags().GetString("context")
	debug, _ := cmd.Flags().GetBool("debug")

	// Load config to get instance details
	cfg, err := config.Load()
	if err != nil {
		return nil, fmt.Errorf("failed to load config: %w", err)
	}

	var instance *config.Instance
	// Use context if specified, otherwise use default
	if contextName != "" {
		instance, err = cfg.GetInstance(contextName)
		if err != nil {
			return nil, fmt.Errorf("context '%s' not found: %w", contextName, err)
		}
	} else {
		instance, err = cfg.GetDefault()
		if err != nil {
			return nil, fmt.Errorf("no default instance configured: %w", err)
		}
	}

	// Get FQDN from instance
	fqdn := instance.FQDN

	// Use token from flag if provided, otherwise use instance token
	if token == "" {
		token = instance.Token
	}

	// Auto-login: if still no token, run device auth transparently
	if token == "" {
		fmt.Println("Not authenticated. Starting browser login...")

		result, err := RunDeviceAuth(fqdn)
		if err != nil {
			return nil, fmt.Errorf("authentication failed: %w", err)
		}

		token = result.Token

		// Save token to config so subsequent commands don't need to re-auth
		if saveErr := SaveAuthToConfig(fqdn, token); saveErr != nil {
			fmt.Printf("Warning: authenticated but failed to save token: %v\n", saveErr)
		} else {
			// Reload config so the in-memory instance is up to date
			if reloaded, reloadErr := config.Load(); reloadErr == nil {
				cfg = reloaded
			}
		}

		fmt.Printf("\nAuthenticated as %s (team: %s)\n\n", result.UserName, result.TeamName)
	}

	// Create client
	client := api.NewClient(fqdn, token, api.WithDebug(debug))

	return client, nil
}
