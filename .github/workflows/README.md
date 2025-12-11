# GitHub Actions Workflow Documentation

This repository includes a comprehensive GitHub Actions workflow that automates the WordPress build process and runs all automation scripts.

## Workflow Overview

The workflow (`php.yml`) includes three main jobs:

### 1. Build Job (Main)
Runs on every push/PR to `main` or `staging` branches:
- ✅ Validates and installs Composer dependencies
- ✅ Builds WordPress from scratch using `build.sh`
- ✅ Verifies build output
- ✅ Sets up MySQL database service
- ✅ Runs database migrations (if migrations exist)
- ✅ Downloads images from R2 (if credentials are configured)

### 2. Migrations Job (Optional)
Runs only when:
- Manually triggered via workflow_dispatch
- Commit message contains `[run-migrations]`

This job provides a dedicated environment for testing migrations.

### 3. Image Sync Job (Optional)
Runs only when:
- Manually triggered via workflow_dispatch
- Commit message contains `[sync-images]`

This job uploads local images to R2 storage.

## Required Secrets

To enable full functionality, configure these secrets in your GitHub repository:

### R2 Storage (for image sync)
1. Go to: **Settings → Secrets and variables → Actions**
2. Add the following secrets:
   - `R2_ACCESS_KEY_ID` - Your Cloudflare R2 access key
   - `R2_SECRET_ACCESS_KEY` - Your Cloudflare R2 secret key
   - `R2_ENDPOINT` - Your R2 endpoint URL
   - `R2_BUCKET_NAME` - Your R2 bucket name

### Optional: Database Credentials
If you want to use a different database configuration, you can override the default test database by setting:
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_HOST`

## Usage Examples

### Standard Build
Every push automatically triggers the build:
```bash
git push origin main
```

### Run Migrations Only
To trigger the migrations job, include `[run-migrations]` in your commit message:
```bash
git commit -m "Update database schema [run-migrations]"
git push
```

### Sync Images to R2
To trigger the image sync job, include `[sync-images]` in your commit message:
```bash
git commit -m "Add new product images [sync-images]"
git push
```

### Manual Trigger
You can also manually trigger any job via GitHub Actions UI:
1. Go to **Actions** tab
2. Select **WordPress Build & Automation**
3. Click **Run workflow**
4. Choose the branch and which jobs to run

## Workflow Behavior

### Build Job
- **Always runs** on push/PR
- **Fails fast** if build fails
- **Continues** if migrations fail (non-blocking)
- **Continues** if image download fails (non-blocking)

### Migrations
- Runs automatically in build job if migrations exist
- Uses MySQL service provided by GitHub Actions
- Creates `wp-config.php` automatically for testing
- Skips if no migrations found

### Image Sync
- Downloads images during build if R2 credentials are set
- Uploads images only when explicitly triggered
- Uses ETag/MD5 comparison to skip unchanged files

## Troubleshooting

### Build Fails
- Check Composer dependencies are valid
- Verify PHP version compatibility
- Review build logs for specific errors

### Migrations Fail
- Verify MySQL service is running
- Check migration file syntax
- Ensure WP-CLI is installed correctly

### Image Sync Fails
- Verify R2 secrets are configured correctly
- Check R2 bucket permissions
- Review network connectivity

## Customization

### Change PHP Version
Edit the `PHP_VERSION` environment variable in the workflow file:
```yaml
env:
  PHP_VERSION: '8.3'  # Change to your preferred version
```

### Add More Triggers
Modify the `on:` section:
```yaml
on:
  push:
    branches: [ "main", "staging", "develop" ]
  schedule:
    - cron: '0 0 * * *'  # Daily builds
```

### Skip Migrations
If you want to skip migrations in the build job, add a condition:
```yaml
- name: Run database migrations
  if: github.event_name != 'pull_request'
  run: ...
```

## Environment Variables

The workflow uses these environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `PHP_VERSION` | `8.3` | PHP version to use |
| `COMPOSER_NO_INTERACTION` | `1` | Disable Composer prompts |
| `DB_NAME` | `wordpress_test` | Database name |
| `DB_USER` | `wordpress` | Database user |
| `DB_PASSWORD` | `wordpress` | Database password |
| `DB_HOST` | `127.0.0.1:3306` | Database host |

## Best Practices

1. **Always test locally** before pushing
2. **Use descriptive commit messages** to trigger optional jobs
3. **Keep secrets secure** - never commit credentials
4. **Monitor workflow runs** to catch issues early
5. **Review build summaries** in GitHub Actions UI

## Support

For issues or questions:
1. Check workflow logs in GitHub Actions
2. Review this documentation
3. Check the main README.md for project-specific details
