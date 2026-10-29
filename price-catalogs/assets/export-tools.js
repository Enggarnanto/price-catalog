/* Shared by the admin UI and the export regression checks. */
((root) => {
 'use strict';
 const safe = value => {
  const text=String(value??'');
  // Quoting alone does not prevent spreadsheet formula execution.
  return /^[\s\uFEFF]*[=+@-]/u.test(text)||/^[\t\r\n]/.test(text)?"'"+text:text;
 };
 function csv(records,type){
  const rows=[];
  const catalog=type==='price_catalog';
  const width=catalog?0:Math.max(0,...records.map(r=>r.content.headers.length));
  const common=['ID','Nama','Deskripsi internal','Sumber','Judul','Deskripsi'];
  rows.push(catalog?[...common,'Mode','Urutan gambar','Attachment ID','URL gambar','Alt','Caption','Judul gambar','Harga','Deskripsi gambar','Link gambar','Tab baru','Label CTA','URL CTA','CTA tab baru']:[...common,'Program','Orientasi','Jenis baris','Nomor baris',...Array.from({length:width},(_,i)=>'Kolom '+(i+1))]);
  for(const record of records){
   const d=record.content,prefix=[record.resource_id,record.metadata.name,record.metadata.description,record.source,d.title,d.description];
   if(catalog){
    const items=d.items.length?d.items:[null];
    items.forEach((item,i)=>rows.push(item?[...prefix,d.mode,i+1,item.attachment_id,item.image_url,item.alt,item.caption,item.title,item.price,item.description,item.link,item.new_tab,item.cta.label,item.cta.url,item.cta.new_tab]:[...prefix,d.mode,...Array(14).fill('')]));
   }else{
    const append=(label,index,cells)=>rows.push([...prefix,d.program,d.orientation,label,index,...cells,...Array(width-cells.length).fill('')]);
    append('Header','',d.headers);
    d.rows.forEach((row,i)=>append('Data',i+1,row.map(c=>c.text)));
   }
  }
  return '\uFEFF'+rows.map(row=>row.map(value=>'"'+safe(value).replace(/"/g,'""')+'"').join(',')).join('\r\n')+'\r\n';
 }
 function file(records,type,format){
  if(!records.length)throw new Error('Tidak ada data untuk diexport.');
  if(!['csv','json'].includes(format))throw new Error('Format export tidak valid.');
  return {text:format==='csv'?csv(records,type):JSON.stringify({format:'lpm-export',version:1,exported_at:new Date().toISOString(),resource_type:type,resources:records},null,2),mime:format==='csv'?'text/csv;charset=utf-8':'application/json;charset=utf-8'};
 }
 const api={file};
 if(typeof module==='object'&&module.exports)module.exports=api;else root.LPMExport=api;
})(typeof window==='object'?window:globalThis);
