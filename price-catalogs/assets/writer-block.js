/* global wp, LPMWriterPicker */
(() => {
 'use strict';
 const {createElement:h}=wp.element;
 const Panel=wp.editor?.PluginDocumentSettingPanel||wp.editPost?.PluginDocumentSettingPanel;
 if(!Panel)return;
 wp.plugins.registerPlugin('lpm-pricelist',{
  render:()=>h(Panel,{name:'lpm-pricelist',title:'Pricelist',icon:'tag'},
   h('p',null,'Cari nama paket atau ID tabel/katalog yang sudah terbit. Pilih tampilan judul dan deskripsi sebelum menyisipkan shortcode.'),
   h(wp.components.Button,{variant:'secondary',onClick:()=>LPMWriterPicker.open({block:true})},'Sisipkan Pricelist'))
 });
})();
