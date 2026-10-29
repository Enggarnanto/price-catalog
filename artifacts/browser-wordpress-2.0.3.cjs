// Real admin.js, real REST handlers, WordPress, and MySQL on loopback only.
const {chromium}=require('C:/Users/Megatron/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('node:assert/strict');
let checks=0;
const check=(value,label)=>{assert.ok(value,label);console.log('PASS '+(++checks)+' '+label);};
(async()=>{
const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
const context=await browser.newContext({viewport:{width:1440,height:1050}}),page=await context.newPage(),errors=[],reads=[];
page.on('pageerror',e=>errors.push(e.message));
page.on('request',r=>{if(r.url().includes('/wp-json/lpm/v1/')&&r.method()==='GET'&&!new URL(r.url()).searchParams.has('_testRequest'))reads.push(r.url());});
const idle=()=>page.waitForFunction(()=>document.querySelector('#lpm-root')&&!document.querySelector('#lpm-root').inert);
const click=async act=>{await page.locator(`[data-act="${act}"]`).first().click();await idle();};
const input=path=>page.locator(`[data-path="${path}"]`);
const api=(path,body)=>page.evaluate(async({path,body})=>{const response=await fetch(LPM.root+path+(path.includes('?')?'&':'?')+'_testRequest=1',{method:body?'POST':'GET',headers:{'X-WP-Nonce':LPM.nonce,'Content-Type':'application/json'},...(body?{body:JSON.stringify(body)}:{})});const data=await response.json();if(!response.ok)throw Error(JSON.stringify(data));return data;},{path,body});
const get=()=>api('/resources/'+id+'?_test='+Date.now());
const remoteSave=async mutate=>{const e=await get(),content=structuredClone(e.content),metadata={name:e.resource.name,description:e.resource.description||''};mutate(content,metadata);return api('/resources/'+id+'/save',{revision:Number(e.resource.draft_revision),base:e.base,content,metadata});};
await page.goto('http://127.0.0.1:18317/');await idle();
await click('new');await page.locator('input[name=name]').fill('Browser workflow '+Date.now());await page.locator('#lpm-create button').click();await idle();
const id=Number(new URL(page.url()).searchParams.get('lpm_resource'));
check(id>0,'Create table through real WordPress UI');
await input('program').selectOption('reguler');await input('headers.0').fill('Paket');
await input('rows.0.0.text').fill('SD - lokal');await input('title').fill('Harga baru');
await click('add-row');
check(await input('rows.1.0.text').evaluate(el=>el===document.activeElement),'New row focuses directly without fetching server');
await page.keyboard.type('SMP');await page.keyboard.press('Tab');
check(await input('rows.1.1.text').evaluate(el=>el===document.activeElement),'Tab moves between actual grid cells');
await page.keyboard.type('Rp250.000');await page.keyboard.press('Shift+Enter');await page.keyboard.type('per bulan');
check((await input('rows.1.1.text').inputValue()).includes('\n'),'Shift Enter keeps multiline entry');
await click('save');let saved=await get();
check(saved.content.title==='Harga baru'&&saved.content.rows[1][0].text==='SMP','Save persists complete table on real MySQL');
check((await page.locator('#lpm-notice').innerText())==='Draft tersimpan.','Save shows simple success message');
check(await page.locator('[data-act=reload]').count()===0,'Destructive server-reload action is removed');

// External revision changes, without conflicting edits, require no manual reload.
await input('rows.0.0.text').fill('SD disesuaikan');
await remoteSave((content,metadata)=>{metadata.description='Catatan dari sesi lain';});
await click('save');saved=await get();
check(saved.content.rows[0][0].text==='SD disesuaikan'&&saved.resource.description==='Catatan dari sesi lain','Disjoint concurrent edits merge and save without losing either input');

// Change the server after the preflight read but before the POST takes its lock.
await input('title').fill('Harga setelah race');let race=true;
await page.route('**/wp-json/lpm/v1/resources/'+id+'/save',async route=>{if(race){race=false;const e=await context.request.get('http://127.0.0.1:18317/wp-json/lpm/v1/resources/'+id,{headers:{'X-WP-Nonce':await page.evaluate(()=>LPM.nonce)}});const r=await e.json();await context.request.post('http://127.0.0.1:18317/wp-json/lpm/v1/resources/'+id+'/save',{headers:{'X-WP-Nonce':await page.evaluate(()=>LPM.nonce)},data:{content:r.content,metadata:{name:r.resource.name,description:'Race: perubahan lain'},revision:Number(r.resource.draft_revision),base:r.base}});}await route.continue();});
await click('save');await page.unroute('**/wp-json/lpm/v1/resources/'+id+'/save');saved=await get();
check(saved.content.title==='Harga setelah race'&&saved.resource.description==='Race: perubahan lain','Real 409 race retries automatically without dropping input');

// A real same-field conflict needs one explicit choice, never silent overwrite.
await input('title').fill('Judul saya');await remoteSave(content=>{content.title='Judul sesi lain';});
await page.locator('[data-act=save]').click();await page.getByRole('dialog').waitFor();
await page.locator('dialog button[value=cancel]').click();await idle();
check(await input('title').inputValue()==='Judul saya'&&(await get()).content.title==='Judul sesi lain','Cancelling conflict keeps local input and server draft unchanged');
await page.locator('[data-act=save]').click();await page.locator('dialog button[value=ok]').click();await idle();
check((await get()).content.title==='Judul saya','Confirmed conflict choice saves with current revision');
check(await page.evaluate(()=>Object.keys(localStorage).some(k=>k.endsWith('_previous'))),'Replaced remote draft is backed up in browser');

// Drop a successful save response: server commits, client keeps input, next save recovers.
await input('title').fill('Simpan saat jaringan putus');
await page.route('**/wp-json/lpm/v1/resources/'+id+'/save',async route=>{await route.fetch();await route.fulfill({status:503,json:{message:'Koneksi terputus setelah commit'}});});
await click('save');await page.unroute('**/wp-json/lpm/v1/resources/'+id+'/save');
check(await input('title').inputValue()==='Simpan saat jaringan putus','Lost save response preserves input');
await page.reload();await idle();
check(await input('title').inputValue()==='Simpan saat jaringan putus','Reload after lost response retains saved or recovered input');
await click('save');check((await get()).content.title==='Simpan saat jaringan putus','Retry after lost receipt succeeds without manual version handling');

// Unsaved browser backup recovers automatically.
await input('rows.0.0.text').fill('Isian sebelum refresh');await page.reload();await idle();
check(await input('rows.0.0.text').inputValue()==='Isian sebelum refresh','Unsaved input restores automatically after page reload');
await click('save');
const old=await get();old.content.title='Pulihkan cadangan versi lama';
await page.evaluate(({id,old})=>sessionStorage.setItem('lpm_local_'+encodeURIComponent(LPM.root)+'_'+id,JSON.stringify({revision:0,content:old.content,metadata:{name:old.resource.name,description:old.resource.description||''}})),{id,old});
await page.reload();await idle();
check(await input('title').inputValue()==='Pulihkan cadangan versi lama','Old v2.0.2 browser backup restores without manual download');
await page.locator('[data-act=save]').click();await page.getByRole('dialog').waitFor();
check((await get()).content.title!=='Pulihkan cadangan versi lama','Unknown old backup does not silently overwrite current server draft');
await page.locator('dialog button[value=ok]').click();await idle();
check((await get()).content.title==='Pulihkan cadangan versi lama','One confirmation safely saves recovered old-version input');

await click('import-editor');
await page.locator('#csv-file').setInputFiles({name:'prices.csv',mimeType:'text/csv',buffer:Buffer.from('Paket,Harga\nCSV SD,100\nCSV SMP,200')});
await page.locator('#csv-preview').click();await page.waitForFunction(()=>!document.querySelector('#csv-apply').disabled);
await page.locator('#csv-apply').click();await page.locator('dialog').last().locator('button[value=ok]').click();await idle();
check((await get()).content.rows[0][0].text==='CSV SD','CSV import saves draft to real database');

await click('publish');await page.locator('[data-act=commit]').waitFor();
check(await page.locator('#lpm-summary').inputValue()==='','Publication note is optional');
const historyRoute=url=>url.pathname==='/wp-json/lpm/v1/resources/'+id+'/history';await page.route(historyRoute,route=>route.fulfill({status:503,json:{message:'History unavailable'}}));
await click('commit');await page.unroute(historyRoute);saved=await get();
check(saved.production_content.rows[1][1].text==='200'&&saved.resource.production_version_id,'Publish succeeds without a required summary');
check((await page.locator('#lpm-notice').innerText()).includes('Berhasil dipublikasikan'),'History request failure cannot turn verified publication into a failed operation');

// Simulate a lost publish response and verify the original operation key is reused.
await input('title').fill('Publish retry');await click('publish');
let publicationKeys=[];
await page.route('**/wp-json/lpm/v1/resources/'+id+'/publish',async route=>{publicationKeys.push(route.request().postDataJSON().operation_key);await route.fetch();await route.fulfill({status:503,json:{message:'Respons publish terputus'}});});
await click('commit');await page.unroute('**/wp-json/lpm/v1/resources/'+id+'/publish');
await page.route('**/wp-json/lpm/v1/resources/'+id+'/publish',async route=>{publicationKeys.push(route.request().postDataJSON().operation_key);await route.continue();});
await click('retry-operation');await page.unroute('**/wp-json/lpm/v1/resources/'+id+'/publish');saved=await get();
check(publicationKeys[0]===publicationKeys[1]&&Number(saved.resource.last_version_number)===2,'Lost publish response retries once with same identity and no duplicate version');

// Restore production while an old draft is open, then publish that draft normally.
await input('title').fill('Draft lama tetap ada');await click('save');
const hist=await api('/resources/'+id+'/history');const e=await get();
const proof=await api('/resources/'+id+'/review?source='+hist[1].id);
await api('/resources/'+id+'/publish',{revision:proof.revision,production:proof.production,source:proof.source,review_token:proof.review_token,summary:'Restore from test',operation_key:'restore_'+Date.now()+'_browser_test'});
await click('publish');await page.locator('[data-act=commit]').waitFor();await click('commit');saved=await get();
check(saved.production_content.title==='Draft lama tetap ada'&&Number(saved.resource.last_version_number)===4,'Stale draft is reconciled behind normal preview/publish flow');

await page.screenshot({path:'artifacts/editor-desktop-2.0.3.png',fullPage:true});
await page.setViewportSize({width:390,height:844});
check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Real WordPress mobile editor has no page-level overflow');
await page.screenshot({path:'artifacts/editor-mobile-2.0.3.png',fullPage:true});
await click('back');
check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Real WordPress library fits mobile width');
await page.screenshot({path:'artifacts/library-mobile-2.0.3.png',fullPage:true});
await page.setViewportSize({width:1440,height:1050});await page.screenshot({path:'artifacts/library-desktop-2.0.3.png',fullPage:true});
const del=page.locator(`[data-act=delete][data-id="${id}"]`);await del.click();await page.locator('dialog button[value=cancel]').click();await idle();
check(!(await get()).resource.deleted_at,'Cancel deletion leaves resource intact');
await del.click();await page.locator('dialog button[value=ok]').click();await idle();
check(await page.locator(`[data-act=edit][data-id="${id}"]`).count()===0,'Confirmed delete removes resource from real library');
check(reads.every(u=>new URL(u).searchParams.has('_lpm_fresh'))&&new Set(reads).size===reads.length,'Every editor GET uses a unique cache-busting URL');
check(errors.length===0,'No browser JavaScript errors: '+errors.join(', '));
console.log('SUCCESS '+checks+' real-browser checks on WordPress + MySQL.');
}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
