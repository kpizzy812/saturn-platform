# Auto-Provisioning Architecture Question

## Status: Discussion / Planning

## Question
How should Saturn handle automatic VPS provisioning for new projects/applications?

## Current Approach (Coolify/Saturn - BYOS)
```
User manually adds servers → Saturn connects via SSH → Deploys apps
```
- User is responsible for server management
- Full control over infrastructure
- Suitable for enterprise/self-hosted

## Railway/Heroku Approach (Shared Infrastructure)
```
User creates app → Platform auto-deploys to shared k8s/containers
```
- All apps on shared infrastructure
- Isolation via containers
- User doesn't see/manage servers
- Auto subdomain: `app.up.railway.app`

## Proposed: Auto-Provisioning (Railway-like with dedicated VPS)
```
User creates project
       ↓
Saturn calls Hetzner/DO API → Creates VPS
       ↓
Auto-configures SSH keys
       ↓
Auto-configures DNS: appname.saturn.io → VPS IP
       ↓
Deploys application
       ↓
(On project delete) → Destroys VPS
```

## Implementation Requirements

### 1. Cloud Provider Integration
- [ ] Hetzner Cloud API integration
- [ ] DigitalOcean API integration (optional)
- [ ] AWS EC2 API integration (optional)
- [ ] Store API tokens securely (encrypted in DB)

### 2. VPS Provisioning Logic
- [ ] Create VPS with predefined specs (size based on plan/tier)
- [ ] Auto-install Docker on new VPS
- [ ] Auto-configure SSH keys
- [ ] Auto-configure firewall rules
- [ ] Health check after provisioning

### 3. DNS Management
- [ ] Wildcard DNS setup (`*.saturn.company.com`)
- [ ] Or per-app DNS record creation via Cloudflare/Route53 API
- [ ] SSL certificate auto-generation (Let's Encrypt)

### 4. Resource Lifecycle
- [ ] Auto-scale VPS based on resource usage (optional)
- [ ] Auto-destroy VPS when project is deleted
- [ ] Cost tracking per project

### 5. UI/UX Changes
- [ ] Hide server management from regular users
- [ ] Show only "Create Project" → auto-provisions
- [ ] Display costs/resource usage

## Questions to Decide

1. **One VPS per project or shared VPS?**
   - Railway: shared infrastructure
   - Dedicated: more isolation, higher cost

2. **Which cloud providers to support first?**
   - Hetzner (cheapest, EU-based)
   - DigitalOcean (popular, good API)
   - AWS (enterprise)

3. **DNS strategy?**
   - Wildcard DNS (simpler): `*.saturn.io`
   - Per-app records (more control): requires DNS API

4. **Billing model?**
   - Pass-through cloud costs
   - Fixed tiers (small/medium/large)
   - Pay-per-use

## Temporary Solution (Current)
For now, use localhost server for all deployments:
- All projects deploy to the same VPS where Saturn runs
- Isolation via Docker containers and networks
- Single wildcard DNS: `*.saturn.company.com` → Saturn VPS IP
- Traefik routes by subdomain

## References
- [Hetzner Cloud API Docs](https://docs.hetzner.cloud/)
- [DigitalOcean API Docs](https://docs.digitalocean.com/reference/api/)
- [Railway Architecture](https://docs.railway.app/)
