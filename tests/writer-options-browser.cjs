const {chromium}=require('C:/Users/Megatron/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('node:fs'),assert=require('node:assert/strict');
let checks=0;const check=(v,m)=>{assert.ok(v,m);console.log('PASS '+(++checks)+' '+m);};
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
const page=await browser.newPage({viewport:{width:1280,height:900}}),errors=[],requests=[];
page.on('pageerror',e=>errors.push(e.message));
await page.route('https://shortcode.test/**',route=>{
 const u=new URL(route.request().url());
 if(u.pathname.startsWith('/assets/'))return route.fulfill({body:fs.readFileSync('price-catalogs'+u.pathname),contentType:u.pathname.endsWith('.js')?'application/javascript':'text/css'});
 if(u.pathname==='/api/resources'){
  requests.push(u.search);const catalog=u.searchParams.get('type')==='price_catalog';
  return route.fulfill({json:{total:1,items:[{id:12,name:'Paket Reguler',description:'Harga paket',resource_type:catalog?'price_catalog':'price_table',production_version_id:5,shortcode:'['+(catalog?'katalog-reguler':'paket-reguler')+' id="12"]'}]}});
 }
 const block=u.searchParams.get('editor')==='block';
 return route.fulfill({contentType:'text/html',body:`<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="/assets/writer.css"><h1>${u.searchParams.get('type')||'post'}</h1><textarea id="content">Artikel awal</textarea><button class="lpm-writer-open" data-editor="content">Pilih &amp; sisipkan shortcode</button><script>const LPMWriter={root:'https://shortcode.test/api',nonce:'test',library:'/library',documentation:'/docs',version:'2.0.10',author:'Galih Enggarnanto'};window.inserted=[];${block?`window.wp={data:{select:()=>({getBlockInsertionPoint:()=>({index:0}),canInsertBlockType:()=>true}),dispatch:()=>({insertBlocks:block=>window.inserted.push(block)})},blocks:{createBlock:(type,attrs)=>({type,attrs})}};`:''}</script><script src="/assets/writer.js"></script>`});
});
for(const type of ['post','page']){
 await page.goto('https://shortcode.test/?type='+type);
 await page.locator('#content').evaluate(el=>{el.selectionStart=el.selectionEnd=el.value.length;});
 await page.locator('.lpm-writer-open').click();await page.locator('[data-insert]').waitFor();
 check((await page.locator('dialog code').textContent()).includes('paket-reguler id="12"'),type+': shortcode shows package and ID');
 await page.locator('[name=show_title]').selectOption('0');await page.locator('[name=show_description]').selectOption('1');
 check((await page.locator('[data-copy]').getAttribute('data-copy')).includes('show_title="0" show_description="1"'),type+': copy action includes visibility settings');
 check((await page.locator('dialog footer').textContent()).includes('v2.0.10')&&(await page.locator('dialog footer').textContent()).includes('Galih Enggarnanto'),type+': picker displays plugin version and author');
 await page.locator('[data-insert]').click();
 check((await page.locator('#content').inputValue()).includes('Artikel awal\n[paket-reguler id="12" show_title="0" show_description="1"]'),type+': Classic editor inserts selected shortcode at cursor');
 await page.goto('https://shortcode.test/?type='+type+'&editor=block');
 await page.locator('.lpm-writer-open').click();await page.locator('[data-insert]').waitFor();
 await page.locator('[name=type]').selectOption('price_catalog');await page.waitForFunction(()=>document.querySelector('dialog code')?.textContent.includes('katalog-reguler'));
 await page.locator('[name=show_description]').selectOption('0');await page.locator('[data-insert]').click();
 const inserted=await page.evaluate(()=>window.inserted);
 check(inserted[0].type==='core/shortcode'&&inserted[0].attrs.text.includes('katalog-reguler')&&inserted[0].attrs.text.includes('show_description="0"'),type+': widget detects block editor and inserts catalog Shortcode block');
}
await page.setViewportSize({width:390,height:844});await page.locator('.lpm-writer-open').click();await page.locator('[data-insert]').waitFor();
check(await page.locator('dialog').evaluate(el=>el.getBoundingClientRect().right<=innerWidth&&el.scrollWidth<=el.clientWidth),'Picker and visibility settings fit mobile screen');
await page.screenshot({path:'artifacts/shortcode-mobile-2.0.10.png',fullPage:true});
check(requests.every(q=>q.includes('status=published')),'Picker requests published resources only');
check(!errors.length,'No browser script errors');
console.log('SUCCESS '+checks+' writer option browser checks');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
