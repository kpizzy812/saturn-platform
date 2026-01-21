# Saturn Frontend - Missing Pages Inventory
## Generated: 2026-01-03

This document lists all pages that need to be implemented to achieve feature parity with the Livewire frontend.

---

## CRITICAL: Applications Section (10 core pages missing)

### Priority 1 - Week 1 (Must-Have for MVP)
These are essential for basic application management:

1. **`resources/js/pages/Applications/Index.tsx`**
   - List all applications
   - Filter by project/environment
   - Status indicators
   - Quick actions (deploy, stop, restart)

2. **`resources/js/pages/Applications/Create.tsx`**
   - Create new application form
   - Source selection (Git, Docker, etc.)
   - Initial configuration
   - Destination selection

3. **`resources/js/pages/Applications/Show.tsx`**
   - Application overview/details
   - Current status
   - Resource usage
   - Quick actions panel

### Priority 2 - Week 2 (Core Functionality)
Required for full CRUD operations:

4. **`resources/js/pages/Applications/Settings.tsx`**
   - General settings
   - Application name, description
   - Deployment settings
   - Resource limits

5. **`resources/js/pages/Applications/Configuration.tsx`**
   - Build configuration
   - Dockerfile/Buildpack settings
   - Build arguments
   - Deploy commands

6. **`resources/js/pages/Applications/Variables.tsx`**
   - Environment variables
   - Secrets management
   - Variable grouping
   - Import/export

### Priority 3 - Week 2-3 (Operational Features)
Needed for day-to-day operations:

7. **`resources/js/pages/Applications/Deployments.tsx`**
   - Deployment history
   - Deployment status
   - Trigger new deployments
   - View deployment details

8. **`resources/js/pages/Applications/Logs.tsx`**
   - Runtime logs
   - Log filtering
   - Log streaming
   - Download logs

9. **`resources/js/pages/Applications/BuildLogs.tsx`**
   - Build logs
   - Build history
   - Build failure diagnostics

10. **`resources/js/pages/Applications/Edit.tsx`**
    - Edit application form
    - Update configuration
    - Modify settings

---

## HIGH PRIORITY: Server Pages (16 missing)

These server management pages are missing from the React frontend:

### Missing Server Pages:
1. `resources/js/pages/Servers/Advanced.tsx` - Advanced server settings
2. `resources/js/pages/Servers/Charts.tsx` - Server monitoring charts
3. `resources/js/pages/Servers/CloudflareTunnel.tsx` - Cloudflare tunnel config
4. `resources/js/pages/Servers/Delete.tsx` - Server deletion confirmation
5. `resources/js/pages/Servers/Destinations.tsx` - Deployment destinations
6. `resources/js/pages/Servers/DockerCleanupExecutions.tsx` - Cleanup history
7. `resources/js/pages/Servers/DockerCleanup.tsx` - Docker cleanup settings
8. `resources/js/pages/Servers/LogDrains.tsx` - Log drain configuration
9. `resources/js/pages/Servers/Navbar.tsx` - Server navigation component
10. `resources/js/pages/Servers/Resources.tsx` - Resource overview
11. `resources/js/pages/Servers/Swarm.tsx` - Docker Swarm management
12. `resources/js/pages/Servers/ValidateAndInstall.tsx` - Server validation/setup

**Note**: Some existing pages may cover these features:
- Proxy configuration exists (5 pages)
- Terminal exists
- Sentinel exists (3 pages)

---

## MEDIUM PRIORITY: Database Pages (8 missing)

Missing database management pages:

### Backup Management (5 pages):
1. `resources/js/pages/Databases/BackupEdit.tsx` - Edit backup configuration
2. `resources/js/pages/Databases/BackupExecutions.tsx` - Backup execution history
3. `resources/js/pages/Databases/BackupNow.tsx` - Manual backup trigger
4. `resources/js/pages/Databases/CreateScheduledBackup.tsx` - Create backup schedule
5. `resources/js/pages/Databases/ScheduledBackups.tsx` - List scheduled backups

### Configuration (3 pages):
6. `resources/js/pages/Databases/Configuration.tsx` - Database configuration
7. `resources/js/pages/Databases/InitScript.tsx` - Initialization scripts
8. `resources/js/pages/Databases/Heading.tsx` - Database heading component

**Note**: Core CRUD exists (13 pages at 68% coverage), these are specialized features.

---

## IMMEDIATE FIX REQUIRED: Build Error

**File**: `resources/js/pages/Admin/Users/Show.tsx`
**Issue**: Importing non-existent Tabs components
**Current Import**:
```typescript
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/Tabs';
```

**Available**:
```typescript
export function Tabs({ tabs, defaultIndex, onChange }: TabsProps)
// Uses Headless UI: TabGroup, TabList, TabPanel
```

**Fix Options**:
1. Export individual components from Tabs.tsx (recommended)
2. Refactor Admin/Users/Show.tsx to use existing Tabs API
3. Create separate Radix UI Tabs component

**Estimated Time**: 1-2 hours

---

## Summary Statistics

| Section | React Pages | Livewire Pages | Coverage | Status |
|---------|-------------|----------------|----------|---------|
| Applications | 5 | 15 | 33% | ⚠️ Critical Gap |
| Servers | 13 | 29 | 44% | ⚠️ Incomplete |
| Databases | 13 | 19 | 68% | ✓ Good |
| Services | 15 | 10 | 150% | ✓ Excellent |
| Settings | 28 | 3 | 933% | ✓ Excellent |
| Deployments | 6 | 2 | 300% | ✓ Excellent |
| Admin | 7 | 8 | 87% | ⚠️ 1 broken |
| **TOTAL** | **151** | **186** | **81.2%** | **⚠️ Not Ready** |

---

## Development Roadmap

### Week 1: Critical Path (40 hours)
- [ ] Fix Tabs component build error (2 hours)
- [ ] Applications/Index.tsx (12 hours)
- [ ] Applications/Create.tsx (12 hours)
- [ ] Applications/Show.tsx (14 hours)

### Week 2: Core Features (40 hours)
- [ ] Applications/Settings.tsx (12 hours)
- [ ] Applications/Configuration.tsx (10 hours)
- [ ] Applications/Variables.tsx (10 hours)
- [ ] Applications/Logs.tsx (8 hours)

### Week 3: Advanced Features (40 hours)
- [ ] Applications/Deployments.tsx (12 hours)
- [ ] Applications/BuildLogs.tsx (8 hours)
- [ ] Applications/Edit.tsx (10 hours)
- [ ] Server missing pages (10 hours)

### Week 4: Testing & Polish (40 hours)
- [ ] Fix failing tests (63 tests)
- [ ] E2E testing for Applications
- [ ] Integration testing
- [ ] Bug fixes

### Week 5-6: Additional Features & Rollout (80 hours)
- [ ] Complete remaining Server pages
- [ ] Database backup features
- [ ] Performance optimization
- [ ] Beta testing
- [ ] Gradual rollout

**Total Estimated Effort**: 240 hours (6 weeks)

---

## Recommendation

**DO NOT** remove Livewire frontend until:
1. ✅ Build passes without errors
2. ✅ All Application CRUD pages implemented
3. ✅ Critical Server pages completed
4. ✅ All tests passing
5. ✅ Beta testing successful

**Alternative**: Use phased migration approach - enable React frontend for completed sections (Settings, Services, Databases) while keeping Livewire for incomplete sections (Applications, Servers).
