const http = require('http');

async function check(url, method = 'GET', body = null) {
  return new Promise((resolve) => {
    const parsed = new URL(url);
    const req = http.request({
      hostname: parsed.hostname,
      port: parsed.port,
      path: parsed.pathname + parsed.search,
      method,
      headers: body ? { 'Content-Type': 'application/json' } : {}
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ status: res.statusCode, length: data.length, contentType: res.headers['content-type'], data }));
    });
    req.on('error', (err) => resolve({ error: err.message }));
    if (body) req.write(JSON.stringify(body));
    req.end();
  });
}

(async () => {
  const tests = [
    ['Health Check', 'http://localhost:5000/api/health', 'GET'],
    ['Medicines Search API', 'http://localhost:5000/api/medicines?search=metformin', 'GET'],
    ['Clinical Check API', 'http://localhost:5000/api/check/medicine', 'POST', { medicine_name: 'Metformin', save_to_locker: false }],
    ['Live Insights API', 'http://localhost:5000/api/medical/live-insights/metformin', 'GET'],
    ['Locale API EN', 'http://localhost:5000/api/locales/en', 'GET'],
    ['Locale API TE', 'http://localhost:5000/api/locales/te', 'GET'],
    ['Locale API HI', 'http://localhost:5000/api/locales/hi', 'GET'],
    ['Locale API TA', 'http://localhost:5000/api/locales/ta', 'GET'],
    ['Locale API ML', 'http://localhost:5000/api/locales/ml', 'GET'],
    ['Locale API KN', 'http://localhost:5000/api/locales/kn', 'GET'],
    ['Locale API OR', 'http://localhost:5000/api/locales/or', 'GET'],
    ['Locale API UR', 'http://localhost:5000/api/locales/ur', 'GET'],
    ['HTML Route /', 'http://localhost:5000/', 'GET'],
    ['HTML Route /check', 'http://localhost:5000/check', 'GET'],
    ['HTML Route /medicines', 'http://localhost:5000/medicines', 'GET'],
    ['HTML Route /my-prescriptions', 'http://localhost:5000/my-prescriptions', 'GET'],
    ['HTML Route /patients', 'http://localhost:5000/patients', 'GET'],
    ['HTML Route /login', 'http://localhost:5000/login', 'GET'],
    ['HTML Route /register', 'http://localhost:5000/register', 'GET'],
    ['SEO Robots.txt', 'http://localhost:5000/robots.txt', 'GET'],
    ['SEO Sitemap.xml', 'http://localhost:5000/sitemap.xml', 'GET'],
    ['SEO OG-Image SVG', 'http://localhost:5000/og-image.svg', 'GET']
  ];

  console.log('Testing AushadhSeva endpoints...');
  let allPass = true;
  for (const [name, url, method, body] of tests) {
    const res = await check(url, method, body);
    const pass = res.status === 200;
    if (!pass) allPass = false;
    console.log(`[${pass ? 'OK' : 'FAIL'}] ${name}: status=${res.status || 'ERR'}, length=${res.length || 0}`);
  }
  if (allPass) {
    console.log(`\nAll ${tests.length} tests PASSED successfully!`);
  } else {
    console.log('\nSome tests FAILED.');
  }
})();
