/* global LPMWriter, wp, tinymce */
(() => {
 'use strict';
 let modal=null,controller=null,request=0,context=null,opener=null;
 const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function close(){if(controller)controller.abort();request++;modal.close();modal.remove();modal=null;if(opener?.isConnected)opener.focus();}
 function status(text,error=false){const el=modal.querySelector('[data-status]');el.textContent=text;el.classList.toggle('is-error',error);}
 function updateShortcodes(){
  modal.querySelectorAll('[data-shortcode]').forEach(item=>{
   let code=item.dataset.shortcode;
   for(const key of ['show_title','show_description']){
    const value=modal.querySelector('[name='+key+']').value;
    if(value!=='default')code=code.replace(/\]$/,` ${key}="${value}"]`);
   }
   item.querySelector('code').textContent=code;
   item.querySelector('[data-insert]').dataset.insert=code;
   item.querySelector('[data-copy]').dataset.copy=code;
  });
 }
 async function list(page=1){
  if(controller)controller.abort();controller=new AbortController();const version=++request;
  const query=new URLSearchParams({status:'published',type:modal.querySelector('[name=type]').value,search:modal.querySelector('[name=search]').value.trim(),page:String(page),_lpm_fresh:crypto.randomUUID()});
  const url=new URL(LPMWriter.root,location.href);
  if(url.searchParams.has('rest_route'))url.searchParams.set('rest_route',url.searchParams.get('rest_route').replace(/\/$/,'')+'/resources');
  else url.pathname=url.pathname.replace(/\/$/,'')+'/resources';
  query.forEach((v,k)=>url.searchParams.set(k,v));
  const results=modal.querySelector('[data-results]');results.setAttribute('aria-busy','true');
  results.replaceChildren();modal.querySelector('[data-pages]').replaceChildren();status('Memuat pricelist…');
  try{
   const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':LPMWriter.nonce},signal:controller.signal});
   const data=await response.json();if(version!==request||!modal)return;
   if(!response.ok)throw new Error(data.message||'Pricelist belum dapat dimuat. Coba Cari lagi.');
   // Fail closed if a proxy unexpectedly returns an unfiltered library response.
   const items=data.items.filter(r=>Number(r.production_version_id)>0&&!r.deleted_at);
   results.innerHTML=items.map(r=>`<article class="lpm-writer-item" data-shortcode="${esc(r.shortcode)}"><div><strong>${esc(r.name)}</strong><small>#${Number(r.id)} · ${r.resource_type==='price_table'?'Tabel harga':'Katalog'}${r.program?' · '+esc(r.program):''}</small>${r.description?`<p>${esc(r.description)}</p>`:''}<code>${esc(r.shortcode)}</code></div><div class="lpm-writer-actions"><button type="button" class="button button-primary" data-insert="${esc(r.shortcode)}">Sisipkan</button><button type="button" class="button" data-copy="${esc(r.shortcode)}">Salin shortcode</button></div></article>`).join('');
   updateShortcodes();status(items.length?`${data.total} pricelist ditemukan.`:'Belum ada pricelist terbit yang cocok. Coba kata kunci lain atau hubungi pengelola harga.');
   modal.querySelector('[data-pages]').innerHTML=`<button type="button" class="button" data-page="${page-1}" ${page===1?'disabled':''}>Sebelumnya</button><span>Halaman ${page} / ${Math.max(1,Math.ceil(data.total/20))}</span><button type="button" class="button" data-page="${page+1}" ${page*20>=data.total?'disabled':''}>Berikutnya</button>`;
  }catch(error){if(version===request&&modal&&error.name!=='AbortError')status(error.message,true);}
  finally{if(version===request&&modal)results.removeAttribute('aria-busy');}
 }
 function insert(shortcode){
  try{
   if(context.block){
    const selector=wp.data.select('core/block-editor');
    const point=selector.getBlockInsertionPoint();
    if(!selector.canInsertBlockType('core/shortcode',point.rootClientId))throw new Error('Artikel ini membatasi blok Shortcode. Gunakan Salin shortcode untuk menempelkannya pada area yang diizinkan.');
    wp.data.dispatch('core/block-editor').insertBlocks(wp.blocks.createBlock('core/shortcode',{text:shortcode}),point.index,point.rootClientId);
   }else{
    const editorId=context.editor||'content',visual=window.tinymce?.get(editorId);
    if(visual&&!visual.isHidden()){
     visual.focus();if(context.bookmark)visual.selection.moveToBookmark(context.bookmark);visual.execCommand('mceInsertContent',false,'<p>'+shortcode+'</p>');visual.fire('change');visual.save();
    }else{
     const input=document.getElementById(editorId);if(!input)throw new Error('Editor belum tersedia. Gunakan Salin shortcode.');
     input.focus();input.setSelectionRange(context.start??input.selectionStart,context.end??input.selectionEnd);input.setRangeText('\n'+shortcode+'\n',input.selectionStart,input.selectionEnd,'end');input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));
    }
   }
   close();
  }catch(error){status(error.message,true);}
 }
 function open(options={}){
  if(modal)return;opener=document.activeElement;context={...options};
  if(!context.block){const id=context.editor||'content',input=document.getElementById(id),visual=window.tinymce?.get(id);if(visual&&!visual.isHidden())context.bookmark=visual.selection.getBookmark(2,true);else if(input){context.start=input.selectionStart;context.end=input.selectionEnd;}}
  modal=document.createElement('dialog');modal.className='lpm-writer-dialog';modal.setAttribute('aria-labelledby','lpm-writer-title');
  modal.innerHTML=`<header><div><h2 id="lpm-writer-title">Sisipkan Pricelist</h2><p>Pilih harga yang sudah terbit. Pembaruan harga berikutnya mengikuti shortcode yang sama.</p></div><button type="button" class="button" data-close aria-label="Tutup pilihan Pricelist">Tutup</button></header><form class="lpm-writer-search"><label>Cari pricelist<input name="search" type="search" placeholder="Nama, ID, atau deskripsi…"></label><label>Jenis<select name="type"><option value="price_table">Tabel harga</option><option value="price_catalog">Katalog gambar</option></select></label><button class="button button-primary">Cari</button></form><div class="lpm-writer-search">${['show_title','show_description'].map((key,i)=>`<label>${i===0?'Tampilkan judul di artikel':'Tampilkan deskripsi di artikel'}<select name="${key}"><option value="default">Ikuti pengaturan paket</option><option value="1">Tampilkan</option><option value="0">Sembunyikan</option></select></label>`).join('')}</div><p data-status role="status" aria-live="polite"></p><section data-results aria-label="Hasil pencarian"></section><nav data-pages aria-label="Halaman hasil"></nav><footer><a href="${esc(LPMWriter.library)}" target="_blank" rel="noopener noreferrer">Buka pustaka Pricelist ↗</a><a href="${esc(LPMWriter.documentation)}" target="_blank" rel="noopener noreferrer">Dokumentasi ↗</a><small>Latis Price Catalogs v${esc(LPMWriter.version||'')} &middot; ${esc(LPMWriter.author||'Galih Enggarnanto')}</small></footer>`;
  document.body.append(modal);modal.querySelectorAll('[name=show_title],[name=show_description]').forEach(el=>el.onchange=updateShortcodes);modal.addEventListener('cancel',e=>{e.preventDefault();close();});
  modal.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close();}});
  modal.querySelector('form').onsubmit=e=>{e.preventDefault();list();};modal.querySelector('[name=type]').onchange=()=>list();
  modal.onclick=async e=>{const button=e.target.closest('button');if(!button||button.disabled)return;
   if(button.hasAttribute('data-close'))close();
   else if(button.dataset.page)list(Number(button.dataset.page));
   else if(button.dataset.insert)insert(button.dataset.insert);
   else if(button.dataset.copy){try{await navigator.clipboard.writeText(button.dataset.copy);if(modal)status('Shortcode disalin. Tempel di artikel yang Anda tulis.');}catch(_){if(modal){status('Salin teks shortcode yang ditandai.');const code=button.closest('article').querySelector('code'),range=document.createRange();range.selectNodeContents(code);const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);}}}
  };
  modal.showModal();modal.querySelector('[name=search]').focus();list();
 }
 document.addEventListener('click',e=>{const button=e.target.closest('.lpm-writer-open');if(button){e.preventDefault();open({editor:button.dataset.editor,block:!!window.wp?.data?.select('core/block-editor')?.getBlockInsertionPoint});}});
 window.LPMWriterPicker={open};
})();
