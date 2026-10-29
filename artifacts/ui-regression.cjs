const {chromium}=require('C:/Users/Megatron/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('node:fs'),assert=require('node:assert/strict');
const clone=v=>JSON.parse(JSON.stringify(v));
const cell=text=>({text,url:'',new_tab:false});
let state={resource:{id:3,resource_type:'price_table',name:'Latiseducation - Nasional',description:'',draft_revision:0,draft_data:null,production_version_id:null,last_version_number:0},content:{schema_version:2,title:'Harga Les Privat',show_title:true,description:'',program:'reguler',headers:['Paket','Harga','Keterangan'],rows:[[cell('SD'),cell('Rp150.000'),cell('4 pertemuan')],[cell('SMP'),cell('Rp200.000'),cell('4 pertemuan')]],orientation:'vertical',cta:{label:'',url:'',new_tab:false},style:{inherit:true,values:{}}},base:0,stale:false,cache_pending:0,production_content:null};
let calls=[],errors=[],checks=0,staleSave=false;
function check(value,label){assert.ok(value,label);console.log('PASS '+(++checks)+' '+label);}
(async()=>{
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
const page=await browser.newPage({viewport:{width:1440,height:1000}});
page.on('pageerror',e=>errors.push(e.message));
const html='<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:0 0 0 20px}button,input,textarea,select{font:inherit}</style><link rel="stylesheet" href="/public.css"><link rel="stylesheet" href="/admin.css"><div id="lpm-app"><div id="lpm-notice" role="status"></div><div id="lpm-root"></div></div><script>window.LPM={root:"https://fixture.test/wp-json/lpm/v1",canManage:true,nonce:"test",screen:"lpm",styling:{}};</script><script src="/grid-tools.js"></script><script src="/admin.js"></script></html>';
await page.route('https://fixture.test/**',async route=>{
const req=route.request(),u=new URL(req.url()),path=u.pathname.replace('/wp-json/lpm/v1','');
if(u.pathname.startsWith('/wp-json')){
calls.push(req.method()+' '+path);let result;
if(path==='/resources')result={items:[{...state.resource,program:'reguler',has_draft:true,shortcode:'[latis_price_table id="3"]',updated_local:'16/10/2026 22:00',updater:'Admin'}],total:1,index:{complete:false}};
else if(path==='/resources/3')result=clone(state);
else if(path==='/resources/3/history')result=[];
else if(path==='/resources/3/save'){const b=req.postDataJSON();if(!staleSave){state.content=clone(b.content);Object.assign(state.resource,{draft_revision:b.revision+1,draft_data:'saved',name:b.metadata.name,description:b.metadata.description});}result={persisted:true,revision:b.revision+1,content:clone(b.content)};}
else if(path==='/csv')result={count:2,columns:2,headers:['Paket','Harga'],rows:[[cell('CSV SD'),cell('100')],[cell('CSV SMP'),cell('200')]]};
else throw Error('Unexpected API '+path);
return route.fulfill({json:result});}
if(u.pathname.endsWith('.css')||u.pathname.endsWith('.js'))return route.fulfill({body:fs.readFileSync('price-catalogs/assets'+u.pathname),contentType:u.pathname.endsWith('.css')?'text/css':'application/javascript'});
await route.fulfill({body:html,contentType:'text/html'});
});
const click=async act=>{await page.locator(`[data-act="${act}"]`).first().click();await page.waitForFunction(()=>!document.querySelector('#lpm-root').inert);};
await page.goto('https://fixture.test/admin.php?page=lpm');
await page.waitForSelector('.lpm-library-table');
check(await page.locator('#lpm-app').evaluate(el=>el.getBoundingClientRect().width)>1380,'Desktop workspace fills available width');
await page.screenshot({path:'artifacts/library-desktop-2.0.2.png',fullPage:true});
await click('edit');
await page.locator('[data-path="title"]').fill('Perubahan langsung');
await page.locator('#lpm-summary').count();
const historyBefore=calls.filter(c=>c.includes('/history')).length;
await click('add-row');
check(await page.locator('[data-path="title"]').inputValue()==='Perubahan langsung','Grid update preserves metadata form');
check(calls.filter(c=>c.includes('/history')).length===historyBefore,'Adding row makes no server/history request');
check(await page.locator('[data-path="rows.2.0.text"]').evaluate(el=>el===document.activeElement),'New row receives keyboard focus after unlock');
await page.keyboard.type('SMA');await page.keyboard.press('Tab');
check(await page.locator('[data-path="rows.2.1.text"]').evaluate(el=>el===document.activeElement),'Tab moves directly to next cell');
await page.keyboard.type('Rp300.000');await page.keyboard.press('Shift+Enter');await page.keyboard.type('per bulan');
check((await page.locator('[data-path="rows.2.1.text"]').inputValue()).includes('\n'),'Shift Enter preserves multiline cell entry');
await page.locator('[data-path="rows.0.0.text"]').focus();await page.keyboard.press('Enter');
check(await page.locator('[data-path="rows.1.0.text"]').evaluate(el=>el===document.activeElement),'Enter moves to next row');
await click('add-col');
check(await page.locator('[data-path="headers.3"]').evaluate(el=>el===document.activeElement),'New column header receives focus');
await page.locator('[data-path="headers.3"]').fill('Catatan');
await page.locator('[data-path="rows.0.0.text"]').evaluate(el=>el.dispatchEvent(new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:(()=>{const d=new DataTransfer();d.setData('text/plain','SD baru\t175000\nSMP baru\t225000');return d;})()})));
await page.waitForFunction(()=>!document.querySelector('#lpm-root').inert);
check(await page.locator('[data-path="rows.1.1.text"]').inputValue()==='225000','Spreadsheet paste updates matrix locally');
staleSave=true;
await click('save');
check((await page.locator('#lpm-notice').getAttribute('class')).includes('notice-error'),'Stale server readback is rejected');
check(await page.locator('[data-path="rows.2.0.text"]').inputValue()==='SMA','Failed save retains all local input');
staleSave=false;
await click('save');
check(state.content.title==='Perubahan langsung'&&state.content.rows[2][0].text==='SMA'&&state.content.headers[3]==='Catatan','Save persists new rows, columns, and metadata without reload');
check((await page.locator('#lpm-notice').innerText()).includes('diverifikasi'),'Save reports verified persistence');
check(await page.getByText('Ada cadangan input lokal.').count()===0,'Successful verified save clears stale recovery banner');
await page.screenshot({path:'artifacts/editor-desktop-2.0.2.png',fullPage:true});
await click('import-editor');
await page.locator('#csv-file').setInputFiles({name:'prices.csv',mimeType:'text/csv',buffer:Buffer.from('Paket,Harga\nCSV SD,100\nCSV SMP,200')});
await page.locator('#csv-preview').click();await page.waitForFunction(()=>!document.querySelector('#csv-apply').disabled);
await page.locator('#csv-apply').click();await page.locator('dialog').last().locator('button[value="ok"]').click();
await page.waitForFunction(()=>!document.querySelector('#lpm-root').inert);
check(state.content.rows[0][0].text==='CSV SD'&&state.resource.draft_revision===2,'Import CSV applies AND saves draft while dialog owns request lock');
await page.setViewportSize({width:390,height:844});
check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile editor keeps horizontal overflow inside grid');
await page.screenshot({path:'artifacts/editor-mobile-2.0.2.png',fullPage:true});
await click('back');
check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile library keeps horizontal overflow inside table');
await page.screenshot({path:'artifacts/library-mobile-2.0.2.png',fullPage:true});
check(errors.length===0,'No browser JavaScript errors: '+errors.join(', '));
console.log('SUCCESS '+checks+' browser checks (mock REST; no live WordPress assertion).');
}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

