// Actual admin UI and downloads, with isolated REST fixtures (no production data).
const {chromium}=require('C:/Users/Megatron/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('node:fs'),assert=require('node:assert/strict');
let checks=0;const check=(v,m)=>{assert.ok(v,m);console.log('PASS '+(++checks)+' '+m);};
const common={title:'Harga publik',show_title:true,description:'',style:{inherit:true,values:{}},cta:{label:'',url:'',new_tab:false}};
const table={...common,program:'reguler',orientation:'vertical',headers:['Paket','Harga'],rows:[[{text:'Paket A',url:'',new_tab:false},{text:'Rp 100',url:'',new_tab:false}]]};
const catalog={...common,mode:'single',autoplay:false,interval:5,items:[{title:'Gambar',price:'Rp 200',description:'Detail',attachment_id:7,image_url:'https://export.test/image.jpg',alt:'Harga katalog',caption:'Caption',link:'https://example.com',new_tab:true,cta:{label:'Pesan',url:'https://example.com/order',new_tab:false}}]};
(async()=>{
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
 const page=await browser.newPage({acceptDownloads:true,viewport:{width:1440,height:1000}}),errors=[],reads=[],writes=[];let fail=false;
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://export.test/**',async route=>{
  const req=route.request(),u=new URL(req.url());
  if(u.pathname.startsWith('/assets/'))return route.fulfill({body:fs.readFileSync('price-catalogs'+u.pathname),contentType:u.pathname.endsWith('.js')?'application/javascript':'text/css'});
  if(u.pathname.startsWith('/api/')){
   reads.push(u.pathname+u.search);if(req.method()!=='GET')writes.push(req.method());
   const type=u.searchParams.get('type')||'price_table',id=Number(u.pathname.match(/resources\/(\d+)/)?.[1]||1),isCatalog=id===100;
   const resource=n=>({id:n,name:'Harga '+n,resource_type:n===100?'price_catalog':'price_table',description:'Internal',production_version_id:n+500,draft_data:'{}',draft_revision:1,last_version_number:1,has_draft:1,draft_base_version_id:n+500,shortcode:'[harga-'+n+' id="'+n+'"]',updated_local:'Hari ini'});
   let data;
   if(u.pathname.endsWith('/history'))data=[];
   else if(/\/resources\/\d+$/.test(u.pathname)){
    if(fail)return route.fulfill({status:500,json:{message:'Fixture gagal membaca data'}});
    data={resource:resource(id),content:structuredClone(isCatalog?catalog:table),production_content:{...structuredClone(isCatalog?catalog:table),title:'Versi terbit'},cache_pending:0};
   }else{
    const filtered=u.searchParams.get('search')==='target',p=Number(u.searchParams.get('page')||1);
    data={items:type==='price_catalog'?[resource(100)]:filtered?[resource(1)]:Array.from({length:p===1?20:1},(_,i)=>resource(p===1?i+1:21)),total:type==='price_catalog'||filtered?1:21,index:{complete:true}};
   }
   return route.fulfill({json:data});
  }
  return route.fulfill({contentType:'text/html',body:'<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="/assets/admin.css"><div id="lpm-notice"></div><div id="lpm-root"></div><script>const LPM={root:"https://export.test/api",nonce:"test",canManage:true,screen:"lpm",styling:{}};</script><script src="/assets/grid-tools.js"></script><script src="/assets/export-tools.js"></script><script src="/assets/admin.js"></script>'});
 });
 const idle=()=>page.waitForFunction(()=>!document.querySelector('#lpm-root').inert&&document.querySelector('#lpm-root').innerHTML);
 const start=async(selector)=>{await page.locator(selector).first().click();await page.locator('dialog[open]').waitFor();};
 const download=async(format='json',source='current')=>{
  await page.locator('dialog select[name=format]').selectOption(format);await page.locator('dialog select[name=source]').selectOption(source);
  const event=page.waitForEvent('download');await page.locator('dialog button[value=ok]').click();const d=await event;await idle();return fs.readFileSync(await d.path(),'utf8');
 };
 await page.goto('https://export.test/');await idle();
 check(await page.locator('[data-act=export-data]').count()===21,'Library has bulk export plus an export per row');
 await start('[data-act=export-data]:not([data-id])');let output=JSON.parse(await download());
 check(output.resources.length===21&&reads.some(x=>x.includes('page=2')),'Bulk export includes the next result page');
 await page.locator('[name=search]').fill('target');
 await page.locator('#lpm-filters button').first().click();await idle();
 await start('[data-act=export-data]:not([data-id])');output=JSON.parse(await download());
 check(output.resources.length===1&&reads.some(x=>x.includes('search=target')),'Bulk export respects applied search filters');
 await start('[data-act=export-data][data-id="1"]');output=JSON.parse(await download('json','published'));
 check(output.resources[0].content.title==='Versi terbit'&&output.resources[0].source==='published','Published export excludes draft content');
 await page.locator('[data-act=edit]').first().click();await idle();
 check(await page.locator('[data-path=show_description]').isChecked(),'Existing resource defaults to showing its description');
 await page.locator('[data-path=show_description]').uncheck();
 check((await page.locator('.lpm-shortcode code').textContent()).includes('harga-1 id="1"'),'Editor displays package name and ID in shortcode');
 await page.locator('[data-path=title]').fill('Belum disimpan');
 await start('.lpm-editor-toolbar > [data-act=export-data]');output=JSON.parse(await download());
 check(output.resources[0].content.title==='Belum disimpan'&&output.resources[0].source==='editor','Editor export captures unsaved fields');
 check(output.resources[0].content.show_description===false,'Export preserves description visibility selection');
 check(await page.locator('[data-path=title]').inputValue()==='Belum disimpan','Export preserves editor state');
 await page.screenshot({path:'artifacts/export-editor-2.0.10.png',fullPage:true});
 await page.goto('https://export.test/?page=lpm-catalogs');await idle();await page.locator('[data-act=catalogs]').click();await idle();
 await start('[data-act=export-data][data-id="100"]');const csv=await download('csv');
 check(csv.includes(catalog.items[0].image_url)&&csv.includes(catalog.items[0].cta.url),'Catalog CSV downloads image and CTA URLs');
 await start('[data-act=export-data]:not([data-id])');output=JSON.parse(await download());check(output.resource_type==='price_catalog','Catalog bulk export uses the catalog type');
 await page.setViewportSize({width:390,height:844});await start('[data-act=export-data]:not([data-id])');
 check(await page.locator('dialog').evaluate(e=>e.getBoundingClientRect().right<=innerWidth),'Export dialog fits mobile viewport');
 await page.screenshot({path:'artifacts/export-mobile-2.0.10.png',fullPage:true});
 await page.locator('dialog button[value=cancel]').click();await idle();
 fail=true;await start('[data-act=export-data][data-id="100"]');await page.locator('dialog button[value=ok]').click();await idle();
 check((await page.locator('#lpm-notice').textContent()).includes('Fixture gagal'),'Failed read shows an error without claiming export success');
 check(!writes.length,'Export never sends a mutation request');check(!errors.length,'No browser JavaScript errors');
 console.log('SUCCESS '+checks+' browser export checks');
}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
