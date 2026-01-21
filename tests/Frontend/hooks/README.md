# API Hooks Tests

This directory contains comprehensive tests for all Saturn API hooks.

## Test Files Created

1. **useProjects.test.ts** - Tests for project hooks (18KB, 15 tests)
2. **useServices.test.ts** - Tests for service hooks (24KB, 18 tests)
3. **useDatabases.test.ts** - Tests for database hooks (23KB, 19 tests)
4. **useServers.test.ts** - Tests for server hooks (22KB, 19 tests)
5. **useDeployments.test.ts** - Tests for deployment hooks (26KB, 21 tests)

**Total: 92 tests** covering all API hooks

## Test Coverage

Each test file covers:

### ✅ Passing Tests (43/92)
- **Initial state** - Hooks start with `loading: true` and empty/null data
- **Successful fetch** - Data is fetched and `loading` becomes `false`
- **Error handling** - Network errors and API errors are handled correctly
- **Refetch functionality** - `refetch()` function updates data
- **Create mutations** - Creating new resources works correctly
- **List operations** - Fetching lists of resources

### ⚠️ Failing Tests (49/92)
Most failures are due to timeout issues (5s limit) in tests for:
- Individual resource fetching (useProject, useService, useDatabase, useServer, useDeployment)
- Auto-refresh functionality

## Test Structure

Each test follows this pattern:

```typescript
describe('useHook', () => {
    beforeEach(() => {
        vi.clearAllMocks(); // Reset mocks before each test
    });

    it('should fetch data successfully', async () => {
        // Mock fetch response
        mockFetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockData,
        });

        // Render hook
        const { result } = renderHook(() => useHook());

        // Wait for loading to complete
        await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
        });

        // Verify data and error state
        expect(result.current.data).toEqual(mockData);
        expect(result.current.error).toBe(null);
    });
});
```

## Running Tests

```bash
# Run all hook tests
npm test -- tests/Frontend/hooks/

# Run specific test file
npm test -- tests/Frontend/hooks/useProjects.test.ts

# Run with coverage
npm run test:coverage -- tests/Frontend/hooks/
```

## Known Issues

### Timeout Issues
Some tests timeout after 5 seconds. This occurs in hooks that fetch individual resources by UUID. The issue is related to:
- Async timing in vitest
- Hook mounting before mocks are fully set up
- React useEffect execution order

### Potential Fixes

1. **Increase timeout for slow tests:**
```typescript
it('should fetch a single resource', async () => {
    // ... test code
}, 10000); // 10 second timeout
```

2. **Better mock setup:**
```typescript
beforeEach(() => {
    vi.clearAllMocks();
    mockFetch.mockImplementation(() =>
        Promise.resolve({
            ok: true,
            json: async () => ({}),
        })
    );
});
```

3. **Use MSW (Mock Service Worker)** instead of manual fetch mocking for more reliable API mocking.

## Test Scenarios Covered

### useProjects (15 tests)
- ✅ List all projects
- ✅ Create project
- ✅ Handle errors
- ⚠️ Single project operations (update, delete, environments)

### useServices (18 tests)
- ✅ List all services
- ✅ Create service
- ✅ Handle errors
- ⚠️ Single service operations (start, stop, restart, delete)
- ⚠️ Environment variable management

### useDatabases (19 tests)
- ✅ List all databases
- ✅ Create database
- ✅ Handle errors
- ⚠️ Single database operations (start, stop, restart, delete)
- ⚠️ Backup management

### useServers (19 tests)
- ✅ List all servers
- ✅ Create server
- ✅ Handle errors
- ⚠️ Single server operations (update, delete, validate)
- ⚠️ Server resources and domains

### useDeployments (21 tests)
- ✅ List all deployments
- ✅ List by application
- ✅ Start deployment (normal and forced)
- ✅ Cancel deployment
- ⚠️ Single deployment operations

## Mocking Strategy

Tests use Vitest's `vi.fn()` to mock the global `fetch` function:

```typescript
const mockFetch = vi.fn();
global.fetch = mockFetch;

// In tests:
mockFetch.mockResolvedValueOnce({
    ok: true,
    json: async () => mockData,
});
```

This approach works well for most scenarios but may need refinement for complex async hooks.

## Future Improvements

1. **Add MSW** for more realistic API mocking
2. **Fix timeout issues** by adjusting mock setup
3. **Add integration tests** that test multiple hooks together
4. **Test WebSocket updates** for real-time data
5. **Test concurrent operations** (multiple fetches, mutations during fetch)
6. **Test edge cases** (race conditions, stale data, cache invalidation)

## Contributing

When adding new hooks or modifying existing ones:
1. Add corresponding tests following the existing patterns
2. Ensure all scenarios are covered (initial state, success, error, mutations)
3. Run tests locally before committing
4. Update this README if you add new test files
