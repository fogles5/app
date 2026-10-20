#!/bin/bash

# Vercel Post-Deployment Setup
# Run this after successful Vercel deployment to initialize database

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}🚀 Vercel Post-Deployment Setup${NC}"
echo "=================================="
echo ""

# Get deployment URL from user
read -p "Enter your Vercel deployment URL (e.g., https://hookup.vercel.app): " DEPLOYMENT_URL

# Remove trailing slash if present
DEPLOYMENT_URL="${DEPLOYMENT_URL%/}"

echo ""
echo -e "${YELLOW}Deployment URL:${NC} $DEPLOYMENT_URL"
echo ""

# Test health endpoint
echo "Testing health endpoint..."
HEALTH_RESPONSE=$(curl -s "$DEPLOYMENT_URL/api/health.php")

if echo "$HEALTH_RESPONSE" | grep -q "ok"; then
    echo -e "${GREEN}✅ Application is running${NC}"
    echo "Response: $HEALTH_RESPONSE"
else
    echo -e "${YELLOW}⚠️ Health check returned unexpected response${NC}"
    echo "Response: $HEALTH_RESPONSE"
fi

echo ""
echo "=================================="
echo ""
echo "Next steps:"
echo ""
echo "1. Initialize database schema:"
echo "   mysql -h DB_HOST -u DB_USER -p DB_NAME < database/schema.sql"
echo ""
echo "2. Or use PhpMyAdmin:"
echo "   - Access your database manager"
echo "   - Import database/schema.sql"
echo ""
echo "3. Test the application:"
echo "   Visit: $DEPLOYMENT_URL"
echo ""
echo "4. Create an admin account and configure settings"
echo ""
echo "For more information, see DEPLOY_TO_VERCEL.md"
