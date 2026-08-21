# Restore local grid tiles when PHP GD is unavailable

Million Dollar Script uses PHP GD when WordPress generates local PNG grid tiles. If GD is unavailable, local tile requests can return `404` responses with no image body. An extension or custom integration that calls `imagecreate()` directly may instead report `Call to undefined function imagecreate()`.

Million Dollar Script checks for the GD function before using the local tile renderer, so an uncaught `imagecreate()` fatal normally points to an extension, custom code, or another image workflow. The missing PHP capability and the server-side fix are the same.

## Confirm the symptom

1. Open the affected grid and inspect its network requests in the browser developer tools.
2. Look for failed PNG tile requests, especially WordPress AJAX requests returning `404`.
3. Review the WordPress debug log, the hosting error log, and any fatal-error email for `imagecreate()`, `imagecreatetruecolor()`, or GD-related messages.
4. Check **Tools > Site Health > Info > Media Handling**. WordPress reports the GD version and supported formats when the extension is active.

Run command-line checks only as supporting evidence. PHP CLI and the PHP-FPM or Apache process serving WordPress can load different configuration files.

```bash
php -r 'var_dump(PHP_VERSION, extension_loaded("gd"), function_exists("imagecreate"), function_exists("imagecreatetruecolor"));'
```

## Enable GD for the WordPress runtime

1. Confirm the PHP version assigned to the website in Site Health or the hosting control panel.
2. Enable the version-matched GD extension through the hosting control panel, or ask the hosting provider to enable it for the website's PHP runtime.
3. On a managed server or container, install or build the matching GD package and restart the PHP-FPM or web-server process that handles WordPress. Package names and service commands vary by platform.
4. Recheck Site Health, then retry the affected grid and confirm its tile requests return PNG images successfully.

Do not leave a public `phpinfo()` page online. It exposes detailed server configuration.

## If GD is active but tiles still fail

- Confirm the website and command line use the same PHP version and configuration.
- Check that the configured renderer is the intended local or hosted renderer.
- Confirm WordPress can write generated files beneath its uploads directory without making files or directories world-writable.
- Clear stale page, object, proxy, and CDN caches after restoring the renderer.
- If hosted ImageGrid rendering is configured, verify its connection and delivery status separately. Remote delivery can avoid local tile generation, but a failed remote request may still fall back to the local renderer.

If the error names a specific extension, update that extension and include its name and version when requesting support. Do not edit the extension merely to suppress the error; the image operation will still be unavailable.

## Related resources

- [Troubleshooting](troubleshooting.md): Diagnose other rendering, route, extension, and update issues.
- [PHP `imagecreatetruecolor()` manual](https://www.php.net/manual/en/function.imagecreatetruecolor.php): Review the GD function used by local tile generation.
- [WordPress Site Health](https://wordpress.org/documentation/article/site-health-screen/): Find the active image editor and media capabilities.
