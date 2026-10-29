const {chromium}=require('C:/Users/Megatron/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');const fs=require('fs');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});try{const page=await browser.newPage({viewport:{width:1280,height:1050}}),ids=JSON.parse(fs.readFileSync('tests/runtime/editor-fixture.json'));
for(const [name,id] of Object.entries(ids)){
 await page.goto('http://127.0.0.1:18317/?lpm_resource='+id);await page.frameLocator('#lpm-preview-frame').locator('.lpm-resource').first().waitFor();
 await page.locator('.lpm-preview-panel').scrollIntoViewIfNeeded();await page.frameLocator('#lpm-preview-frame').locator('body').screenshot({path:'artifacts/'+name+'-preview-2.2.0.png'});
 await page.screenshot({path:'artifacts/'+name+'-desktop-2.2.0.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});await page.locator('[data-device=mobile]').click();await page.locator('.lpm-preview-panel').scrollIntoViewIfNeeded();await page.locator('.lpm-preview-panel').screenshot({path:'artifacts/'+name+'-preview-mobile-2.2.0.png'});await page.screenshot({path:'artifacts/'+name+'-mobile-2.2.0.png',fullPage:true});await page.setViewportSize({width:1280,height:1050});
 console.log(name+' visual captured');
}}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
