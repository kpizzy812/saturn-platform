package completion

import (
	"fmt"
	"os"

	"github.com/spf13/cobra"

	"github.com/saturn-platform/saturn-cli/internal/cli"
)

func NewCompletionsCommand() *cobra.Command {
	cmd := &cobra.Command{
		Use:   "completion <shell>",
		Short: "Output shell completion code for the specified shell",
		Long: `To load completions:

### Bash

To load completions into the current shell execute:

    source <(saturn completion bash)

In order to make the completions permanent, append the line above to
your .bashrc.

### Zsh

If shell completions are not already enabled for your environment need
to enable them. Add the following line to your ~/.zshrc file:

    autoload -Uz compinit; compinit

To load completions for each session execute the following commands:

    mkdir -p ~/.config/saturn/completion/zsh
    saturn completion zsh > ~/.config/saturn/completion/zsh/_saturn

Finally add the following line to your ~/.zshrc file, *before* you
call the compinit function:

    fpath+=(~/.config/saturn/completion/zsh)

In the end your ~/.zshrc file should contain the following two lines
in the order given here.

    fpath+=(~/.config/saturn/completion/zsh)
    #  ... anything else that needs to be done before compinit
    autoload -Uz compinit; compinit
    # ...

You will need to start a new shell for this setup to take effect.

### Fish

To load completions into the current shell execute:

    saturn completion fish | source

In order to make the completions permanent execute once:

     saturn completion fish > ~/.config/fish/completions/saturn.fish

### PowerShell:

To load completions into the current shell execute:

  PS> saturn completion powershell | Out-String | Invoke-Expression

To load completions for every new session, run 
and source this file from your PowerShell profile.

  PS> saturn completion powershell > saturn.ps1
`,
		Args:                  cli.ExactArgs(1, "<shell>"),
		ValidArgs:             []string{"bash", "fish", "zsh", "powershell"},
		DisableFlagsInUseLine: true,
		RunE: func(cmd *cobra.Command, args []string) error {
			var err error

			switch args[0] {
			case "bash":
				err = cmd.Root().GenBashCompletion(os.Stdout)
			case "fish":
				err = cmd.Root().GenFishCompletion(os.Stdout, true)
			case "zsh":
				err = cmd.Root().GenZshCompletion(os.Stdout)
			case "powershell":
				err = cmd.Root().GenPowerShellCompletion(os.Stdout)
			default:
				err = fmt.Errorf("Unsupported shell: %s", args[0])
			}
			return err
		},
	}
	return cmd
}
