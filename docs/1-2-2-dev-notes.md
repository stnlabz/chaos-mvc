## PHP 8.5 Compatibility — cURL lifecycle
- Remove the deprecated `curl_close()` call from `app/controllers/site_json.php`. Review confirmed no other ChAoS MVC Core use of `curl_close()`.

## Web Server Compatibility
See PREFLIGHT.md