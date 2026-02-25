package application

import (
	"fmt"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/cli"
	"github.com/saturn-platform/saturn-cli/internal/output"
	"github.com/saturn-platform/saturn-cli/internal/service"
)

// NewRollbackCommand creates the rollback parent command with list and execute subcommands
func NewRollbackCommand() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "rollback",
		Short: "Manage application rollbacks",
		Long:  `List rollback events and execute rollbacks to previous deployments.`,
	}

	cmd.AddCommand(newRollbackListCommand())
	cmd.AddCommand(newRollbackExecuteCommand())

	return cmd
}

func newRollbackListCommand() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "list <app-uuid>",
		Short: "List rollback events for an application",
		Long:  `List all rollback events for a specific application, showing status, commits, and trigger information.`,
		Args:  cli.ExactArgs(1, "<app-uuid>"),
		RunE: func(cmd *cobra.Command, args []string) error {
			ctx := cmd.Context()
			appUUID := args[0]

			client, err := cli.GetAPIClient(cmd)
			if err != nil {
				return fmt.Errorf("failed to get API client: %w", err)
			}

			take, _ := cmd.Flags().GetInt("take")

			deploySvc := service.NewDeploymentService(client)
			events, err := deploySvc.GetRollbackEvents(ctx, appUUID, take)
			if err != nil {
				return fmt.Errorf("failed to list rollback events: %w", err)
			}

			if len(events) == 0 {
				fmt.Println("No rollback events found for this application.")
				return nil
			}

			format, _ := cmd.Flags().GetString("format")
			formatter, err := output.NewFormatter(format, output.Options{})
			if err != nil {
				return fmt.Errorf("failed to create formatter: %w", err)
			}

			return formatter.Format(events)
		},
	}

	cmd.Flags().Int("take", 0, "Number of rollback events to retrieve (0 = all)")
	return cmd
}

func newRollbackExecuteCommand() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "execute <app-uuid> <deployment-uuid>",
		Short: "Execute a rollback to a previous deployment",
		Long:  `Rollback an application to a specific previous deployment by its UUID.`,
		Args:  cli.ExactArgs(2, "<app-uuid> <deployment-uuid>"),
		RunE: func(cmd *cobra.Command, args []string) error {
			ctx := cmd.Context()
			appUUID := args[0]
			deploymentUUID := args[1]

			client, err := cli.GetAPIClient(cmd)
			if err != nil {
				return fmt.Errorf("failed to get API client: %w", err)
			}

			force, _ := cmd.Flags().GetBool("force")

			// Prompt for confirmation unless --force is used
			if !force {
				fmt.Printf("Are you sure you want to rollback application %s to deployment %s? (yes/no): ", appUUID, deploymentUUID)
				var response string
				if _, err := fmt.Scanln(&response); err != nil {
					return fmt.Errorf("failed to read confirmation: %w", err)
				}

				if response != "yes" && response != "y" {
					fmt.Println("Rollback aborted.")
					return nil
				}
			}

			deploySvc := service.NewDeploymentService(client)
			result, err := deploySvc.ExecuteRollback(ctx, appUUID, deploymentUUID)
			if err != nil {
				return fmt.Errorf("failed to execute rollback: %w", err)
			}

			format, _ := cmd.Flags().GetString("format")
			formatter, err := output.NewFormatter(format, output.Options{})
			if err != nil {
				return fmt.Errorf("failed to create formatter: %w", err)
			}

			return formatter.Format(result)
		},
	}

	cmd.Flags().BoolP("force", "f", false, "Skip confirmation prompt")
	return cmd
}
