# Custom Domains Feature - Future Implementation

**Status:** Planned
**Priority:** P3
**Created:** 2026-01-22

---

## Overview

Full custom domain management for applications - allowing users to use their own domains instead of Saturn subdomains.

## User Flow

1. User adds `myapp.com` in application settings
2. Saturn shows DNS instructions: "Add A record `myapp.com` → `185.x.x.x`"
3. User configures DNS at their registrar
4. Saturn verifies DNS propagation
5. Saturn provisions SSL certificate via Let's Encrypt
6. `https://myapp.com` works

## Features to Implement

### 1. DNS Guidance
- Show required DNS records based on server IP
- Support A records and CNAME records
- Copy-to-clipboard functionality

### 2. Domain Verification
- DNS lookup to verify domain points to Saturn server
- Status: Pending → Verified → Failed
- Auto-retry verification periodically

### 3. SSL Status Display
- Query Traefik/Caddy for certificate status
- States: Active, Expiring Soon, Expired, Failed
- Show expiry date

### 4. SSL Certificate Management
- Auto-provision via Let's Encrypt (already works via Traefik/Caddy)
- Manual renewal button for failed auto-renewals
- Show certificate details

### 5. Redirect Settings
- HTTP → HTTPS redirect toggle
- non-www → www redirect toggle (or vice versa)

## Backend Changes Needed

1. Store domain verification status in database
2. API endpoint to check DNS propagation
3. API endpoint to query SSL status from proxy
4. API endpoint to trigger certificate renewal

## Files to Modify

- `app/Models/Application.php` - add domain verification fields
- `routes/api.php` - new endpoints
- `routes/web.php` - pass full domain data to frontend
- `resources/js/pages/Applications/Settings/Domains.tsx` - full UI
- `resources/js/types/models.ts` - Domain type updates

---

**Note:** Current implementation uses simple domain list from `fqdn` field. This feature expands it to full domain management.
