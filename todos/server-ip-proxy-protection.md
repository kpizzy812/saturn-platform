# Server IP Proxy Protection - Future Implementation

**Status:** Planned
**Priority:** P3
**Created:** 2026-01-22
**Source:** Team Lead suggestion

---

## Overview

Implement proxying layer to hide actual server IP addresses from public exposure. When users access applications via custom domains, the real server IP should not be visible.

## Problem

Currently when a user sets up a custom domain pointing to their Saturn server:
- The server's real IP is exposed in DNS records
- DDoS attacks can target the actual infrastructure
- Server location/provider is discoverable

## Potential Solutions

### 1. Cloudflare Integration
- Users add their domain to Cloudflare
- Cloudflare proxies traffic, hiding origin IP
- Free tier available
- Requires user action outside Saturn

### 2. Built-in Proxy Layer
- Saturn provides shared proxy IPs
- Traffic routed through proxy to user servers
- More complex infrastructure needed

### 3. Tunneling (like Cloudflare Tunnel)
- Outbound connection from server to proxy
- No inbound ports needed
- Similar to ngrok/cloudflared

## Research Needed

- [ ] Analyze how Coolify handles this
- [ ] Research cloudflared tunnel integration
- [ ] Cost analysis for proxy infrastructure
- [ ] User experience considerations

## Related Features

- Custom Domains (todos/custom-domains-feature.md)
- SSL Certificate Management

---

**Note:** This is a significant infrastructure feature. Requires careful planning and possibly paid infrastructure.
