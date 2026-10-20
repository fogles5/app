# Vercel Deployment Instructions

## Quick Start

### 1. Prepare Your Repository
```bash
# Make setup script executable
chmod +x scripts/vercel-setup.sh

# Run verification
./scripts/vercel-setup.sh
```

### 2. Set Up Database

Choose one of the recommended providers:

**PlanetScale (Recommended)**
- Sign up: https://planetscale.com
- Create database
- Copy connection string

**AWS RDS**
- Create MySQL instance
- Configure security groups
- Note connection details

**DigitalOcean**
- Create managed MySQL cluster
- Configure firewall
- Copy credentials

### 3. Deploy to Vercel

#### Option A: Via Vercel Dashboard (Easiest)
1. Go to https://vercel.com/dashboard
2. Click "Add New" → "Project"
3. Select "Import Git Repository"
4. Connect your GitHub account
5. Select the hookup repository
6. Add environment variables:
   - `DB_HOST`: your-database-host
   - `DB_NAME`: hookup_prod
   - `DB_USER`: your-username
   - `DB_PASSWORD`: your-password
7. Click "Deploy"

#### Option B: Via Vercel CLI
```bash
# Install Vercel CLI
npm i -g vercel

# Login
vercel login

# Deploy
vercel --prod

# Add environment variables when prompted
```

### 4. Verify Deployment

```bash
# Check deployment status
vercel status

# View logs
vercel logs your-app.vercel.app

# Test the application
curl https://your-app.vercel.app
```

## Environment Variables Needed

| Variable | Example | Description |
|----------|---------|-------------|
| DB_HOST | db.planetscale.com | Database host |
| DB_NAME | hookup_prod | Database name |
| DB_USER | username | Database user |
| DB_PASSWORD | securepass123 | Database password |
| APP_ENV | production | Application environment |

## Database Schema Setup

After deployment, initialize the database schema:

### Using MySQL Client
```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < database/schema.sql
```

### Using PHPMyAdmin
1. Access your database manager
2. Import `database/schema.sql`
3. Run the schema

### Via API (One-time)
Create a temporary endpoint:
```php
// api/init-db.php (delete after use)
if ($_GET['key'] === 'your-secret-key') {
    require_once '../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = file_get_contents('../database/schema.sql');
    $conn->exec($sql);
    
    echo "Database initialized successfully";
}
```

Access: `https://your-app.vercel.app/api/init-db.php?key=your-secret-key`

## Configuration Files Added

### vercel.json
Main Vercel configuration
- PHP 8.2 runtime
- Composer dependency installation
- Environment variable mapping

### .vercelignore
Files to ignore during deployment
- Large ZIP files
- Vendor directories
- Media files

### config/vercel.php
Environment-specific settings
- Production/development mode
- Security headers
- HTTPS enforcement

### config/vercel-sessions.php
Session management for serverless
- Database-backed sessions
- Handles stateless environment

### .github/workflows/vercel-deploy.yml
Automated CI/CD pipeline
- Auto-deploy on push to main
- Environment integration

## Troubleshooting

### Connection Issues
```
Error: SQLSTATE[HY000]: General error: 2006 MySQL server has gone away
```
**Solution:**
- Check firewall allows Vercel IPs
- Verify DB_HOST, DB_USER, DB_PASSWORD
- Ensure database is running

### Session Issues
```
Error: Session data lost between requests
```
**Solution:**
- Verify database-backed sessions are configured
- Check config/vercel-sessions.php is loaded
- Ensure sessions table exists

### Upload Failures
```
Error: Read-only file system
```
**Solution:**
- Vercel filesystem is read-only
- Use external storage (S3, Cloudinary)
- Configure UPLOAD_DRIVER in config

### Timeout Issues
```
Error: Function exceeded maximum execution duration
```
**Solution:**
- Optimize database queries
- Add indexes to frequently queried tables
- Consider caching layer (Redis)

## Monitoring

### View Logs
1. Dashboard → Deployments
2. Select deployment
3. Click "Logs"

### Performance Monitoring
- Vercel Analytics (built-in)
- Enable Web Vitals
- Monitor database performance

### Error Tracking
```php
// Logs go to /tmp/php-errors.log on Vercel
tail -f /tmp/php-errors.log
```

## Scaling

| Plan | Bandwidth | Features |
|------|-----------|----------|
| Free | 100 GB/month | Basic deployment |
| Pro | Unlimited | More functions, priority |
| Enterprise | Custom | Dedicated support |

Upgrade in Vercel dashboard → Settings → Billing

## Custom Domain

1. Dashboard → Domains
2. Add custom domain
3. Follow DNS instructions
4. SSL auto-configured

## Rollback

### Revert to Previous Version
```bash
# List deployments
vercel ls

# Deploy specific commit
vercel --target production SHA
```

## Automation

### GitHub Actions Integration
See `.github/workflows/vercel-deploy.yml`

Configure Vercel token:
1. Generate token: https://vercel.com/account/tokens
2. Add to GitHub → Settings → Secrets
3. Name: `VERCEL_TOKEN`

## Performance Tips

1. **Enable Caching**
   ```php
   header('Cache-Control: public, max-age=3600');
   ```

2. **Optimize Queries**
   - Add database indexes
   - Use prepared statements

3. **Compress Assets**
   - Gzip CSS/JS
   - Optimize images

4. **Use CDN**
   - Vercel edge network (automatic)
   - Configure custom domain

## Backup Strategy

1. **Code**: GitHub repository
2. **Database**: 
   - Enable automated backups
   - Export schema regularly
   - Backup user data

## Support

- Vercel Docs: https://vercel.com/docs
- PHP Runtime: https://vercel.com/docs/functions/runtimes/php
- GitHub Issues: Check repository

---

**Last Updated**: 2026-07-17
**Vercel Docs**: https://vercel.com/docs
