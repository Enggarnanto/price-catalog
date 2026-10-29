<?php
// Loopback-only test surface: real WordPress JS packages and real author REST API.
foreach(['wp-block-library','wp-block-editor','wp-plugins','wp-editor','wp-components'] as $handle){wp_enqueue_script($handle);}
wp_enqueue_script('lpm-writer','/price-catalogs/assets/writer.js',['wp-blocks','wp-data','wp-plugins','wp-editor','wp-element','wp-components','wp-block-library','wp-block-editor'],LPM_VERSION,true);
$root=isset($_GET['plain'])?'http://127.0.0.1:18317/?rest_route=/lpm/v1&_writer_test=1':'http://127.0.0.1:18317/wp-json/lpm/v1?_writer_test=1';
wp_localize_script('lpm-writer','LPMWriter',['root'=>$root,'nonce'=>wp_create_nonce('wp_rest'),'library'=>admin_url('admin.php?page=lpm'),'documentation'=>LPM_Writer::DOCUMENTATION]);
wp_enqueue_script('lpm-writer-block','/price-catalogs/assets/writer-block.js',['lpm-writer'],LPM_VERSION,true);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/price-catalogs/assets/writer.css"><?php wp_print_head_scripts(); ?></head><body>
<h1>Local writer test</h1><textarea id="content" rows="5" style="width:90%">Artikel awal.</textarea><p><button class="lpm-writer-open" data-editor="content">Sisipkan Pricelist</button><button id="block-picker">Buka panel blok</button></p>
<?php wp_print_footer_scripts(); ?>
<script>
wp.blockLibrary.registerCoreBlocks();
wp.data.dispatch('core/block-editor').updateSettings({allowedBlockTypes:true,templateLock:false});
document.getElementById('block-picker').onclick=()=>{const node=wp.plugins.getPlugin('lpm-pricelist').render();node.props.children[1].props.onClick();};
</script></body></html>
