package version

import (
	"fmt"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/version"
)

// NewVersionCommand creates the version command
func NewVersionCommand() *cobra.Command {
	return &cobra.Command{
		Use:   "version",
		Short: "Current Saturn CLI version",
		Run: func(_ *cobra.Command, _ []string) {
			fmt.Println(version.GetVersion())
		},
	}
}
