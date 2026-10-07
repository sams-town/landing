<?php
header("HTTP/1.1 403 Forbidden");
die("<!doctype html><html><head><title>403</title><style>body{background:#1A2540;color:#FFF8F0;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center}
.box{max-width:480px;padding:30px}h1{font-size:48px;margin:0 0 10px;color:#FF6B4A}h2{margin:0 0 20px;font-size:22px;opacity:0.9}p{opacity:0.75;line-height:1.6;font-size:14px}
a{color:#FF6B4A;font-weight:700;text-decoration:underline}</style></head><body><div class='box'>
<h1>🐾 403</h1><h2>Deployment folder not for public access.</h2>
<p>Please delete this <code>/deploy/</code> folder immediately via File Manager after your install script completes. Until then, only the installer PHP files themselves are accessible — not this index.</p>
<p><a href='../'>← Go back to homepage</a></p>
</div></body></html>");
