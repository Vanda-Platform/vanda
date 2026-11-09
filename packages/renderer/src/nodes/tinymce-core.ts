// TinyMCE 6 (MIT; 7.x and later are GPL or commercial, keep to 6.x). Its plugins, skins and language
// packs are plain scripts that find the editor in `window.tinymce`, so this module is imported first:
// its body sets the global before any of them runs.
import tinymce from 'tinymce';

export default tinymce;
