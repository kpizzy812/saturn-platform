package deployment

import (
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestNewSmartCommand_Flags(t *testing.T) {
	cmd := NewSmartCommand()

	// Verify command metadata
	assert.Equal(t, "smart", cmd.Use)
	assert.NotEmpty(t, cmd.Short)
	assert.NotEmpty(t, cmd.Long)

	// Verify all expected flags exist
	flags := []struct {
		name      string
		shorthand string
	}{
		{"base", ""},
		{"yes", "y"},
		{"force", "f"},
		{"init", ""},
		{"dry-run", ""},
		{"wait", "w"},
		{"timeout", ""},
		{"poll-interval", ""},
	}

	for _, f := range flags {
		flag := cmd.Flags().Lookup(f.name)
		require.NotNil(t, flag, "flag --%s should exist", f.name)
		if f.shorthand != "" {
			assert.Equal(t, f.shorthand, flag.Shorthand, "flag --%s shorthand", f.name)
		}
	}
}

func TestNewSmartCommand_FlagDefaults(t *testing.T) {
	cmd := NewSmartCommand()

	// Check defaults
	base, err := cmd.Flags().GetString("base")
	require.NoError(t, err)
	assert.Empty(t, base)

	yes, err := cmd.Flags().GetBool("yes")
	require.NoError(t, err)
	assert.False(t, yes)

	force, err := cmd.Flags().GetBool("force")
	require.NoError(t, err)
	assert.False(t, force)

	initFlag, err := cmd.Flags().GetBool("init")
	require.NoError(t, err)
	assert.False(t, initFlag)

	dryRun, err := cmd.Flags().GetBool("dry-run")
	require.NoError(t, err)
	assert.False(t, dryRun)

	wait, err := cmd.Flags().GetBool("wait")
	require.NoError(t, err)
	assert.False(t, wait)

	timeout, err := cmd.Flags().GetInt("timeout")
	require.NoError(t, err)
	assert.Equal(t, 600, timeout)

	pollInterval, err := cmd.Flags().GetInt("poll-interval")
	require.NoError(t, err)
	assert.Equal(t, 3, pollInterval)
}

func TestSmartCommand_RegisteredInDeployment(t *testing.T) {
	cmd := NewDeploymentCommand()

	// Verify the smart subcommand is registered
	found := false
	for _, sub := range cmd.Commands() {
		if sub.Use == "smart" {
			found = true
			break
		}
	}
	assert.True(t, found, "smart command should be registered as subcommand of deploy")
}
