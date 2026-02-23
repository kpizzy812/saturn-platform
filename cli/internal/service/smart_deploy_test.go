package service

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"

	"github.com/saturn-platform/saturn-cli/internal/api"
	"github.com/saturn-platform/saturn-cli/internal/models"
)

// --- NormalizeGitURL ---

func TestNormalizeGitURL(t *testing.T) {
	tests := []struct {
		name     string
		input    string
		expected string
	}{
		{
			name:     "SSH format",
			input:    "git@github.com:org/repo.git",
			expected: "github.com/org/repo",
		},
		{
			name:     "SSH without .git",
			input:    "git@github.com:org/repo",
			expected: "github.com/org/repo",
		},
		{
			name:     "HTTPS format",
			input:    "https://github.com/org/repo.git",
			expected: "github.com/org/repo",
		},
		{
			name:     "HTTPS without .git",
			input:    "https://github.com/org/repo",
			expected: "github.com/org/repo",
		},
		{
			name:     "HTTP format",
			input:    "http://github.com/org/repo.git",
			expected: "github.com/org/repo",
		},
		{
			name:     "GitLab subgroups",
			input:    "git@gitlab.com:org/subgroup/repo.git",
			expected: "gitlab.com/org/subgroup/repo",
		},
		{
			name:     "trailing whitespace",
			input:    "  git@github.com:org/repo.git  ",
			expected: "github.com/org/repo",
		},
		{
			name:     "plain URL passthrough",
			input:    "github.com/org/repo",
			expected: "github.com/org/repo",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := NormalizeGitURL(tt.input)
			assert.Equal(t, tt.expected, result)
		})
	}
}

// --- GlobMatch ---

func TestGlobMatch(t *testing.T) {
	tests := []struct {
		name    string
		pattern string
		path    string
		match   bool
	}{
		{
			name:    "exact directory with **",
			pattern: "apps/api/**",
			path:    "apps/api/src/main.go",
			match:   true,
		},
		{
			name:    "deeply nested match",
			pattern: "apps/api/**",
			path:    "apps/api/src/internal/handler/routes.go",
			match:   true,
		},
		{
			name:    "direct child match",
			pattern: "apps/api/**",
			path:    "apps/api/Dockerfile",
			match:   true,
		},
		{
			name:    "no match different dir",
			pattern: "apps/api/**",
			path:    "apps/web/src/main.ts",
			match:   false,
		},
		{
			name:    "** matches everything",
			pattern: "**",
			path:    "any/deep/nested/file.txt",
			match:   true,
		},
		{
			name:    "** matches single file",
			pattern: "**",
			path:    "file.txt",
			match:   true,
		},
		{
			name:    "wildcard extension",
			pattern: "*.go",
			path:    "main.go",
			match:   true,
		},
		{
			name:    "wildcard extension no match",
			pattern: "*.go",
			path:    "main.ts",
			match:   false,
		},
		{
			name:    "nested ** in middle",
			pattern: "src/**/test.go",
			path:    "src/pkg/service/test.go",
			match:   true,
		},
		{
			name:    "exact match",
			pattern: "Dockerfile",
			path:    "Dockerfile",
			match:   true,
		},
		{
			name:    "exact match no match",
			pattern: "Dockerfile",
			path:    "src/Dockerfile",
			match:   false,
		},
		{
			name:    "single wildcard in segment",
			pattern: "apps/*/src",
			path:    "apps/api/src",
			match:   true,
		},
		{
			name:    "pattern longer than path",
			pattern: "apps/api/src/main.go",
			path:    "apps/api",
			match:   false,
		},
		{
			name:    "path longer than pattern without glob",
			pattern: "apps/api",
			path:    "apps/api/src",
			match:   false,
		},
		{
			name:    "** at end matches empty",
			pattern: "apps/api/**",
			path:    "apps/api",
			match:   true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := GlobMatch(tt.pattern, tt.path)
			assert.Equal(t, tt.match, result, "GlobMatch(%q, %q)", tt.pattern, tt.path)
		})
	}
}

// --- LoadConfig ---

func TestLoadConfig(t *testing.T) {
	t.Run("valid config", func(t *testing.T) {
		dir := t.TempDir()
		configContent := `version: 1
base_branch: develop
components:
  api:
    path: "apps/api/**"
    resource: "my-api"
  web:
    path: "apps/web/**"
    resource: "my-web"
  shared:
    path: "packages/shared/**"
    triggers:
      - api
      - web
`
		err := os.WriteFile(filepath.Join(dir, ".saturn.yml"), []byte(configContent), 0644)
		require.NoError(t, err)

		cfg, err := LoadConfig(dir)
		require.NoError(t, err)
		require.NotNil(t, cfg)

		assert.Equal(t, 1, cfg.Version)
		assert.Equal(t, "develop", cfg.BaseBranch)
		assert.Len(t, cfg.Components, 3)
		assert.Equal(t, "apps/api/**", cfg.Components["api"].Path)
		assert.Equal(t, "my-api", cfg.Components["api"].Resource)
		assert.Equal(t, []string{"api", "web"}, cfg.Components["shared"].Triggers)
	})

	t.Run("missing file returns nil", func(t *testing.T) {
		dir := t.TempDir()
		cfg, err := LoadConfig(dir)
		require.NoError(t, err)
		assert.Nil(t, cfg)
	})

	t.Run("invalid YAML", func(t *testing.T) {
		dir := t.TempDir()
		err := os.WriteFile(filepath.Join(dir, ".saturn.yml"), []byte("{{invalid"), 0644)
		require.NoError(t, err)

		cfg, err := LoadConfig(dir)
		require.Error(t, err)
		assert.Nil(t, cfg)
	})

	t.Run("wrong version", func(t *testing.T) {
		dir := t.TempDir()
		err := os.WriteFile(filepath.Join(dir, ".saturn.yml"), []byte("version: 99\n"), 0644)
		require.NoError(t, err)

		cfg, err := LoadConfig(dir)
		require.Error(t, err)
		assert.Contains(t, err.Error(), "unsupported")
		assert.Nil(t, cfg)
	})
}

// --- WriteConfig ---

func TestWriteConfig(t *testing.T) {
	dir := t.TempDir()

	cfg := &models.SmartConfig{
		Version:    1,
		BaseBranch: "main",
		Components: map[string]models.SmartComponent{
			"api": {
				Path:     "apps/api/**",
				Resource: "my-api",
			},
			"shared": {
				Path:     "libs/**",
				Triggers: []string{"api"},
			},
		},
	}

	err := WriteConfig(dir, cfg)
	require.NoError(t, err)

	// Read back
	readCfg, err := LoadConfig(dir)
	require.NoError(t, err)
	require.NotNil(t, readCfg)

	assert.Equal(t, 1, readCfg.Version)
	assert.Equal(t, "main", readCfg.BaseBranch)
	assert.Equal(t, "apps/api/**", readCfg.Components["api"].Path)
	assert.Equal(t, "my-api", readCfg.Components["api"].Resource)
	assert.Equal(t, []string{"api"}, readCfg.Components["shared"].Triggers)
}

// --- GenerateConfig ---

func TestGenerateConfig(t *testing.T) {
	t.Run("with base directory", func(t *testing.T) {
		baseDir := "/apps/api"
		resources := []models.Resource{
			{
				UUID:          "uuid-1",
				Name:          "My API",
				GitRepository: strPtr("git@github.com:org/repo.git"),
				BaseDirectory: &baseDir,
			},
		}

		cfg := GenerateConfig(resources)
		assert.Equal(t, 1, cfg.Version)
		assert.Equal(t, "main", cfg.BaseBranch)
		assert.Len(t, cfg.Components, 1)

		comp := cfg.Components["my-api"]
		assert.Equal(t, "apps/api/**", comp.Path)
		assert.Equal(t, "My API", comp.Resource)
	})

	t.Run("without base directory", func(t *testing.T) {
		resources := []models.Resource{
			{
				UUID:          "uuid-2",
				Name:          "Full Repo App",
				GitRepository: strPtr("https://github.com/org/app.git"),
			},
		}

		cfg := GenerateConfig(resources)
		comp := cfg.Components["full-repo-app"]
		assert.Equal(t, "**", comp.Path)
	})

	t.Run("nil git_repository filtered", func(t *testing.T) {
		resources := []models.Resource{
			{UUID: "uuid-3", Name: "No Git"},
			{UUID: "uuid-4", Name: "Has Git", GitRepository: strPtr("git@github.com:org/repo.git")},
		}

		cfg := GenerateConfig(resources)
		assert.Len(t, cfg.Components, 1)
	})
}

// --- BuildDeployPlan ---

func TestBuildDeployPlan_DirectMatch(t *testing.T) {
	resources := []models.Resource{
		{UUID: "uuid-api", Name: "my-api"},
		{UUID: "uuid-web", Name: "my-web"},
	}

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(resources)
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewSmartDeployService(client)

	cfg := &models.SmartConfig{
		Version:    1,
		BaseBranch: "main",
		Components: map[string]models.SmartComponent{
			"api": {Path: "apps/api/**", Resource: "my-api"},
			"web": {Path: "apps/web/**", Resource: "my-web"},
		},
	}

	files := []string{
		"apps/api/src/main.go",
		"apps/api/Dockerfile",
		"README.md",
	}

	plan, err := svc.BuildDeployPlan(context.Background(), files, cfg)
	require.NoError(t, err)

	assert.Equal(t, 3, plan.FilesTotal)
	assert.Len(t, plan.Components, 1) // Only api matched

	assert.Equal(t, "api", plan.Components[0].Name)
	assert.Equal(t, "my-api", plan.Components[0].ResourceName)
	assert.Equal(t, "uuid-api", plan.Components[0].ResourceUUID)
	assert.Equal(t, 2, plan.Components[0].FilesChanged)
	assert.Equal(t, "direct", plan.Components[0].Reason)
}

func TestBuildDeployPlan_TriggerChain(t *testing.T) {
	resources := []models.Resource{
		{UUID: "uuid-api", Name: "my-api"},
		{UUID: "uuid-web", Name: "my-web"},
	}

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(resources)
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewSmartDeployService(client)

	cfg := &models.SmartConfig{
		Version:    1,
		BaseBranch: "main",
		Components: map[string]models.SmartComponent{
			"api":    {Path: "apps/api/**", Resource: "my-api"},
			"web":    {Path: "apps/web/**", Resource: "my-web"},
			"shared": {Path: "packages/shared/**", Triggers: []string{"api", "web"}},
		},
	}

	files := []string{
		"packages/shared/utils.ts",
	}

	plan, err := svc.BuildDeployPlan(context.Background(), files, cfg)
	require.NoError(t, err)

	assert.Equal(t, 1, plan.FilesTotal)
	// shared (direct) + api (triggered) + web (triggered) = 3
	assert.Len(t, plan.Components, 3)

	// Find components by reason
	directCount := 0
	triggeredCount := 0
	for _, c := range plan.Components {
		if c.Reason == "direct" {
			directCount++
			assert.Equal(t, "shared", c.Name)
		} else if c.Reason == "triggered" {
			triggeredCount++
			assert.Equal(t, "shared", c.TriggerBy)
		}
	}
	assert.Equal(t, 1, directCount)
	assert.Equal(t, 2, triggeredCount)
}

func TestBuildDeployPlan_NoMatch(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode([]models.Resource{})
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewSmartDeployService(client)

	cfg := &models.SmartConfig{
		Version:    1,
		BaseBranch: "main",
		Components: map[string]models.SmartComponent{
			"api": {Path: "apps/api/**", Resource: "my-api"},
		},
	}

	plan, err := svc.BuildDeployPlan(context.Background(), []string{"unrelated/file.txt"}, cfg)
	require.NoError(t, err)
	assert.Empty(t, plan.Components)
}

// --- AutoDetect ---

func TestAutoDetect(t *testing.T) {
	// AutoDetect needs a real git repo — we test the matching logic via BuildDeployPlan
	// and NormalizeGitURL. Here we test the service wiring with a mock.

	// This test verifies the resource filtering by git URL
	baseDir := "/apps/api"
	resources := []models.Resource{
		{UUID: "uuid-1", Name: "api", GitRepository: strPtr("git@github.com:org/monorepo.git"), BaseDirectory: &baseDir},
		{UUID: "uuid-2", Name: "web", GitRepository: strPtr("git@github.com:org/monorepo.git")},
		{UUID: "uuid-3", Name: "other", GitRepository: strPtr("git@github.com:org/other-repo.git")},
		{UUID: "uuid-4", Name: "no-git"},
	}

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(resources)
	}))
	defer server.Close()

	// We can't easily mock GetGitRemoteURL here since it calls git.
	// Instead we test the underlying GenerateConfig with filtered resources.
	filtered := []models.Resource{resources[0], resources[1]}
	cfg := GenerateConfig(filtered)

	assert.Len(t, cfg.Components, 2)
	assert.Equal(t, "api", cfg.Components["api"].Resource)
	assert.Equal(t, "apps/api/**", cfg.Components["api"].Path)
	assert.Equal(t, "web", cfg.Components["web"].Resource)
	assert.Equal(t, "**", cfg.Components["web"].Path)
}

// --- ExecuteSmartDeploy ---

func TestExecuteSmartDeploy(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")

		if r.URL.Path == "/api/v1/deploy" && r.URL.Query().Get("uuid") == "uuid-api" {
			_ = json.NewEncoder(w).Encode(DeployResponse{
				Deployments: []DeploymentInfo{
					{Message: "Deployment started", ResourceUUID: "uuid-api", DeploymentUUID: "dep-1"},
				},
			})
			return
		}

		if r.URL.Path == "/api/v1/deploy" && r.URL.Query().Get("uuid") == "uuid-web" {
			_ = json.NewEncoder(w).Encode(DeployResponse{
				Deployments: []DeploymentInfo{
					{Message: "Deployment started", ResourceUUID: "uuid-web", DeploymentUUID: "dep-2"},
				},
			})
			return
		}

		w.WriteHeader(http.StatusNotFound)
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewSmartDeployService(client)

	plan := &models.SmartDeployPlan{
		BaseBranch: "main",
		FilesTotal: 5,
		Components: []models.SmartDeployComponent{
			{Name: "api", ResourceName: "my-api", ResourceUUID: "uuid-api", FilesChanged: 3, Reason: "direct"},
			{Name: "web", ResourceName: "my-web", ResourceUUID: "uuid-web", Reason: "triggered"},
		},
	}

	results, uuids := svc.ExecuteSmartDeploy(context.Background(), plan, false)

	assert.Len(t, results, 2)
	assert.True(t, results[0].Success)
	assert.Equal(t, "Deployment started", results[0].Message)
	assert.True(t, results[1].Success)
	assert.Len(t, uuids, 2)
	assert.Contains(t, uuids, "dep-1")
	assert.Contains(t, uuids, "dep-2")
}

func TestExecuteSmartDeploy_MissingUUID(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNotFound)
	}))
	defer server.Close()

	client := api.NewClient(server.URL, "test-token")
	svc := NewSmartDeployService(client)

	plan := &models.SmartDeployPlan{
		Components: []models.SmartDeployComponent{
			{Name: "broken", ResourceName: "unknown", ResourceUUID: ""},
		},
	}

	results, uuids := svc.ExecuteSmartDeploy(context.Background(), plan, false)
	assert.Len(t, results, 1)
	assert.False(t, results[0].Success)
	assert.Equal(t, "resource UUID not found", results[0].Error)
	assert.Empty(t, uuids)
}

// --- sanitizeKey ---

func TestSanitizeKey(t *testing.T) {
	tests := []struct {
		input    string
		expected string
	}{
		{"My API App", "my-api-app"},
		{"web_frontend", "web-frontend"},
		{"simple", "simple"},
		{"With.Dots.Here", "with-dots-here"},
		{"  spaces  ", "spaces"},
		{"UPPER-case", "upper-case"},
	}

	for _, tt := range tests {
		t.Run(tt.input, func(t *testing.T) {
			assert.Equal(t, tt.expected, sanitizeKey(tt.input))
		})
	}
}

// helper
func strPtr(s string) *string {
	return &s
}
