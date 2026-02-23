package deployment

import (
	"fmt"
	"os"
	"strings"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/cli"
	"github.com/saturn-platform/saturn-cli/internal/models"
	"github.com/saturn-platform/saturn-cli/internal/service"
)

// NewSmartCommand creates the smart deploy subcommand
func NewSmartCommand() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "smart",
		Short: "Smart monorepo deploy — only deploy changed components",
		Long: `Analyze git diff to detect changed monorepo components and deploy only what changed.

Uses .saturn.yml config if present, otherwise auto-detects from Saturn API resources
by matching the local git remote URL.

Use --init to generate a .saturn.yml from your Saturn resources.

Example .saturn.yml:
  version: 1
  base_branch: main
  components:
    api:
      path: "apps/api/**"
      resource: "my-api-app"
    web:
      path: "apps/web/**"
      resource: "my-frontend"
    shared:
      path: "packages/shared/**"
      triggers: ["api", "web"]`,
		RunE: runSmart,
	}

	cmd.Flags().String("base", "", "Base branch for diff (default: from .saturn.yml or \"main\")")
	cmd.Flags().BoolP("yes", "y", false, "Skip confirmation prompt")
	cmd.Flags().BoolP("force", "f", false, "Force rebuild all matched components")
	cmd.Flags().Bool("init", false, "Generate .saturn.yml from Saturn API resources")
	cmd.Flags().Bool("dry-run", false, "Show deploy plan without deploying")
	AddWaitFlags(cmd)

	return cmd
}

func runSmart(cmd *cobra.Command, _ []string) error {
	ctx := cmd.Context()
	initMode, _ := cmd.Flags().GetBool("init")

	client, err := cli.GetAPIClient(cmd)
	if err != nil {
		return fmt.Errorf("failed to get API client: %w", err)
	}

	smartSvc := service.NewSmartDeployService(client)

	// --init: generate .saturn.yml and exit
	if initMode {
		return handleInit(cmd, smartSvc)
	}

	// Load config or auto-detect
	dir, err := os.Getwd()
	if err != nil {
		return fmt.Errorf("failed to get working directory: %w", err)
	}

	cfg, err := service.LoadConfig(dir)
	if err != nil {
		return err
	}

	if cfg == nil {
		fmt.Fprintln(cmd.OutOrStdout(), "No .saturn.yml found, auto-detecting from API...")
		cfg, err = smartSvc.AutoDetect(ctx)
		if err != nil {
			return err
		}
		fmt.Fprintf(cmd.OutOrStdout(), "Detected %d component(s)\n", len(cfg.Components))
	}

	// Determine base branch
	base, _ := cmd.Flags().GetString("base")
	if base == "" {
		base = cfg.BaseBranch
	}
	if base == "" {
		base = "main"
	}

	// Get changed files
	files, err := service.GetChangedFiles(cmd.Context(), base)
	if err != nil {
		return err
	}

	if len(files) == 0 {
		fmt.Fprintf(cmd.OutOrStdout(), "No files changed between %s and HEAD\n", base)
		return nil
	}

	fmt.Fprintf(cmd.OutOrStdout(), "%d file(s) changed since %s\n", len(files), base)

	// Build deploy plan
	plan, err := smartSvc.BuildDeployPlan(ctx, files, cfg)
	if err != nil {
		return err
	}

	if len(plan.Components) == 0 {
		fmt.Fprintln(cmd.OutOrStdout(), "No components matched the changed files")
		return nil
	}

	// Display plan
	printPlan(cmd, plan)

	// --dry-run: stop here
	dryRun, _ := cmd.Flags().GetBool("dry-run")
	if dryRun {
		return nil
	}

	// Confirm
	yes, _ := cmd.Flags().GetBool("yes")
	if !yes {
		fmt.Fprint(cmd.OutOrStdout(), "\nProceed with deployment? [y/N] ")
		var answer string
		if _, err := fmt.Fscanln(cmd.InOrStdin(), &answer); err != nil {
			answer = ""
		}
		if !strings.HasPrefix(strings.ToLower(answer), "y") {
			fmt.Fprintln(cmd.OutOrStdout(), "Aborted")
			return nil
		}
	}

	// Execute deployment
	force, _ := cmd.Flags().GetBool("force")
	results, allUUIDs := smartSvc.ExecuteSmartDeploy(ctx, plan, force)

	// Display results
	printResults(cmd, results)

	// Summary
	successCount := 0
	for _, r := range results {
		if r.Success {
			successCount++
		}
	}

	fmt.Fprintf(cmd.OutOrStdout(), "\nSmart deploy complete: %d/%d succeeded\n", successCount, len(results))

	if successCount < len(results) {
		return fmt.Errorf("some deployments failed")
	}

	// Handle --wait
	return HandleWait(cmd, smartSvc.DeploymentService(), allUUIDs)
}

func handleInit(cmd *cobra.Command, smartSvc *service.SmartDeployService) error {
	ctx := cmd.Context()

	cfg, err := smartSvc.AutoDetect(ctx)
	if err != nil {
		return err
	}

	dir, err := os.Getwd()
	if err != nil {
		return fmt.Errorf("failed to get working directory: %w", err)
	}

	if err := service.WriteConfig(dir, cfg); err != nil {
		return err
	}

	fmt.Fprintf(cmd.OutOrStdout(), "Generated .saturn.yml with %d component(s)\n", len(cfg.Components))
	for name, comp := range cfg.Components {
		fmt.Fprintf(cmd.OutOrStdout(), "  %s: path=%q resource=%q\n", name, comp.Path, comp.Resource)
	}

	return nil
}

func printPlan(cmd *cobra.Command, plan *models.SmartDeployPlan) {
	fmt.Fprintf(cmd.OutOrStdout(), "\nDeploy Plan (%d component(s), %d file(s) changed):\n", len(plan.Components), plan.FilesTotal)
	fmt.Fprintf(cmd.OutOrStdout(), "%-20s %-25s %-8s %-10s %s\n", "COMPONENT", "RESOURCE", "FILES", "REASON", "TRIGGER")
	fmt.Fprintf(cmd.OutOrStdout(), "%-20s %-25s %-8s %-10s %s\n", strings.Repeat("-", 20), strings.Repeat("-", 25), strings.Repeat("-", 8), strings.Repeat("-", 10), strings.Repeat("-", 15))

	for _, c := range plan.Components {
		trigger := ""
		if c.TriggerBy != "" {
			trigger = c.TriggerBy
		}
		fmt.Fprintf(cmd.OutOrStdout(), "%-20s %-25s %-8d %-10s %s\n", c.Name, c.ResourceName, c.FilesChanged, c.Reason, trigger)
	}
}

func printResults(cmd *cobra.Command, results []models.SmartDeployResult) {
	fmt.Fprintln(cmd.OutOrStdout(), "\nDeploy Results:")
	for _, r := range results {
		if r.Success {
			fmt.Fprintf(cmd.OutOrStdout(), "  [OK]   %s (%s): %s\n", r.Name, r.ResourceName, r.Message)
		} else {
			fmt.Fprintf(cmd.ErrOrStderr(), "  [FAIL] %s (%s): %s\n", r.Name, r.ResourceName, r.Error)
		}
	}
}
