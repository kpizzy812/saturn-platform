package github

import (
	"fmt"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/cli"
	"github.com/saturn-platform/saturn-cli/internal/output"
	"github.com/saturn-platform/saturn-cli/internal/service"
)

func NewListCommand() *cobra.Command {
	return &cobra.Command{
		Use:   "list",
		Short: "List all GitHub App integrations",
		Long:  `List all GitHub App integrations configured in Saturn.`,
		RunE: func(cmd *cobra.Command, _ []string) error {
			ctx := cmd.Context()

			client, err := cli.GetAPIClient(cmd)
			if err != nil {
				return fmt.Errorf("failed to get API client: %w", err)
			}

			svc := service.NewGitHubAppService(client)
			apps, err := svc.List(ctx)
			if err != nil {
				return fmt.Errorf("failed to list GitHub Apps: %w", err)
			}

			format, _ := cmd.Flags().GetString("format")
			formatter, err := output.NewFormatter(format, output.Options{})
			if err != nil {
				return err
			}

			return formatter.Format(apps)
		},
	}
}
