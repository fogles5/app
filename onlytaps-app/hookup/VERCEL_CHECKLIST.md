# Vercel Deployment Checklist

## Pre-Deployment

- [ ] Repository is up to date
- [ ] All configuration files are present
- [ ] composer.json is configured
- [ ] No secrets committed to repository
- [ ] .vercelignore file exists
- [ ] vercel.json is configured correctly

## Database Setup

- [ ] Database created (PlanetScale/AWS RDS/DigitalOcean)
- [ ] Database credentials obtained
- [ ] Firewall configured to allow Vercel IPs
- [ ] Connection string tested locally

## Vercel Configuration

- [ ] Vercel account created
- [ ] GitHub repository connected to Vercel
- [ ] Environment variables added:
  - [ ] DB_HOST
  - [ ] DB_NAME
  - [ ] DB_USER
  - [ ] DB_PASSWORD
  - [ ] APP_ENV (set to 'production')

## Deployment

- [ ] Project deployed successfully
- [ ] Deployment logs reviewed for errors
- [ ] Application URL accessible
- [ ] Health check endpoint responds: `https://your-app.vercel.app/api/health.php`

## Post-Deployment

- [ ] Database schema initialized
- [ ] Admin account created
- [ ] Application settings configured
- [ ] Session handling verified
- [ ] File uploads tested (if applicable)

## Security

- [ ] HTTPS enabled (automatic on Vercel)
- [ ] Security headers verified
- [ ] Environment variables not exposed
- [ ] Database credentials secured
- [ ] .env file added to .gitignore

## Monitoring

- [ ] Error logs accessible
- [ ] Application monitoring enabled
- [ ] Database performance checked
- [ ] SSL certificate valid

## Documentation

- [ ] DEPLOY_TO_VERCEL.md reviewed
- [ ] VERCEL_DEPLOYMENT.md reviewed
- [ ] Team notified of deployment
- [ ] Backup strategy documented

## Rollback Plan

- [ ] Previous version accessible
- [ ] Rollback procedure documented
- [ ] Database backup available
- [ ] Team aware of rollback process

---

## Useful Commands

```bash
# Verify setup
./scripts/vercel-setup.sh

# Deploy to production
vercel --prod

# View logs
vercel logs https://your-app.vercel.app

# Check deployment status
vercel status

# List deployments
vercel ls

# Post-deployment setup
./scripts/vercel-post-deploy.sh
```

## Quick Reference

| Item | Value |
|------|-------|
| Platform | Vercel |
| Runtime | PHP 8.2 |
| Framework | Pure PHP |
| Database | MySQL 5.7+ |
| Health Check | `/api/health.php` |
| Deployment | Automatic on push to main |

---

**Last Updated**: 2026-07-17
**Status**: ✅ Ready for Deployment
