package deployment

import (
	"fmt"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/cli"
	"github.com/saturn-platform/saturn-cli/internal/output"
	"github.com/saturn-platform/saturn-cli/internal/service"
)

// NewTagCommand deploys a resource by tag name
func NewTagCommand() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "tag <tag-name>",
		Short: "Deploy by tag name",
		Long:  `Deploy all resources associated with a specific tag. This allows deploying multiple related applications at once.`,
		Args:  cli.ExactArgs(1, "<tag-name>"),
		RunE: func(cmd *cobra.Command, args []string) error {
			ctx := cmd.Context()
			tag := args[0]

			client, err := cli.GetAPIClient(cmd)
			if err != nil {
				return fmt.Errorf("failed to get API client: %w", err)
			}

			force, _ := cmd.Flags().GetBool("force")

			deploySvc := service.NewDeploymentService(client)
			result, err := deploySvc.DeployByTag(ctx, tag, force)
			if err != nil {
				return fmt.Errorf("failed to deploy by tag: %w", err)
			}

			format, _ := cmd.Flags().GetString("format")
			formatter, err := output.NewFormatter(format, output.Options{})
			if err != nil {
				return err
			}

			// For table format, convert deployment info array to display format
			if format == output.FormatTable {
				displays := make([]ResultDisplay, len(result.Deployments))
				for i, dep := range result.Deployments {
					displays[i] = ResultDisplay{
						Message:        dep.Message,
						DeploymentUUID: dep.DeploymentUUID,
					}
				}
				if err := formatter.Format(displays); err != nil {
					return err
				}
			} else {
				if err := formatter.Format(result); err != nil {
					return err
				}
			}

			// Handle --wait flag
			return HandleWait(cmd, deploySvc, CollectDeploymentUUIDs(result))
		},
	}

	cmd.Flags().Bool("force", false, "Force deployment")
	AddWaitFlags(cmd)
	return cmd
}
