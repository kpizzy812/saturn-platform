.PHONY: build test lint vet fmt sync release clean help

# Build
build: ## Build saturn binary
	go build -o saturn ./saturn/main.go

# Testing
test: ## Run all tests
	go test ./... -count=1

test-v: ## Run all tests (verbose)
	go test ./... -v -count=1

test-race: ## Run tests with race detector
	go test ./... -race -count=1

test-cover: ## Run tests with coverage
	go test ./... -coverprofile=coverage.out
	go tool cover -html=coverage.out -o coverage.html
	@echo "Coverage report: coverage.html"

# Code quality
lint: ## Run golangci-lint
	golangci-lint run ./...

vet: ## Run go vet
	go vet ./...

fmt: ## Run gofmt check
	@diff=$$(gofmt -d -s .); \
	if [ -n "$$diff" ]; then \
		echo "$$diff"; \
		echo "Run 'make fmt-fix' to fix"; \
		exit 1; \
	fi

fmt-fix: ## Fix gofmt issues
	gofmt -w -s .

check: fmt vet test ## Run all checks (fmt + vet + test)

# Sync & Release
sync: ## Sync CLI code to saturn-cli repo
	./scripts/sync-to-cli-repo.sh

sync-dry: ## Dry run of sync (show changes without pushing)
	./scripts/sync-to-cli-repo.sh --dry-run

release: ## Sync + create release (usage: make release TAG=v1.5.0)
	@if [ -z "$(TAG)" ]; then \
		echo "Usage: make release TAG=v1.5.0"; \
		exit 1; \
	fi
	./scripts/sync-to-cli-repo.sh --release $(TAG)

release-dry: ## Dry run of release (usage: make release-dry TAG=v1.5.0)
	@if [ -z "$(TAG)" ]; then \
		echo "Usage: make release-dry TAG=v1.5.0"; \
		exit 1; \
	fi
	./scripts/sync-to-cli-repo.sh --release $(TAG) --dry-run

# Utilities
clean: ## Remove build artifacts
	rm -f saturn coverage.out coverage.html

tidy: ## Run go mod tidy
	go mod tidy

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
