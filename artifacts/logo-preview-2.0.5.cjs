const {chromium}=require('C:/Users/Megatron/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('node:fs'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});try{
const page=await browser.newPage({viewport:{width:1440,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.route('https://logo.test/**',async route=>{const u=new URL(route.request().url());if(u.pathname.startsWith('/wp-json/'))return route.fulfill({json:{items:[],total:0,index:{complete:true}}});
if(/^\/(admin\.css|public\.css|admin\.js|grid-tools\.js|logo\.png)$/.test(u.pathname))return route.fulfill({body:fs.readFileSync('price-catalogs/assets'+u.pathname),contentType:u.pathname.endsWith('.css')?'text/css':u.pathname.endsWith('.png')?'image/png':'application/javascript'});
return route.fulfill({contentType:'text/html',body:'<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding-left:20px}</style><link rel="stylesheet" href="/admin.css"><div id="lpm-app"><div id="lpm-notice"></div><div id="lpm-root"></div></div><script>window.LPM={root:"https://logo.test/wp-json/lpm/v1",nonce:"fixture",canManage:true,logo:"https://logo.test/logo.png"}</script><script src="/grid-tools.js"></script><script src="/admin.js"></script>'});});
await page.goto('https://logo.test/');await page.waitForSelector('.lpm-brand-logo');await page.locator('.lpm-brand-logo').evaluate(img=>img.decode());
assert(await page.locator('.lpm-brand-logo').evaluate(img=>img.naturalWidth>0&&img.clientWidth===44));
await page.screenshot({path:'artifacts/logo-desktop-2.0.5.png'});
await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:'artifacts/logo-mobile-2.0.5.png'});
assert.equal(errors.length,0);console.log('PASS original logo loads at 44px; desktop/mobile header, overflow, and JavaScript checked.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
