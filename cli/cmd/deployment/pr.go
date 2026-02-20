package deployment

import (
	"fmt"
	"strconv"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/cli"
	"github.com/saturn-platform/saturn-cli/internal/output"
	"github.com/saturn-platform/saturn-cli/internal/service"
)

// NewPRCommand deploys a PR preview for an application
func NewPRCommand() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "pr <app-uuid> <pr-id>",
		Short: "Deploy a PR preview",
		Long:  `Deploy a pull request preview for a specific application. This creates a preview deployment for the given PR number.`,
		Args:  cli.ExactArgs(2, "<app-uuid> <pr-id>"),
		RunE: func(cmd *cobra.Command, args []string) error {
			ctx := cmd.Context()
			uuid := args[0]

			prID, err := strconv.Atoi(args[1])
			if err != nil {
				return fmt.Errorf("invalid PR ID %q: must be a number", args[1])
			}

			client, err := cli.GetAPIClient(cmd)
			if err != nil {
				return fmt.Errorf("failed to get API client: %w", err)
			}

			force, _ := cmd.Flags().GetBool("force")

			deploySvc := service.NewDeploymentService(client)
			result, err := deploySvc.DeployByPR(ctx, uuid, prID, force)
			if err != nil {
				return fmt.Errorf("failed to deploy PR preview: %w", err)
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
