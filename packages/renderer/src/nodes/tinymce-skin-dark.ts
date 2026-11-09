// TinyMCE's dark skin, loaded only by a page in the dark theme: its toolbars and dialogs (`ui`)
// and the CSS of the text inside the editor's frame (`content`).
import contentUi from 'tinymce/skins/ui/oxide-dark/content.min.css?raw';
import ui from 'tinymce/skins/ui/oxide-dark/skin.min.css?raw';
import content from 'tinymce/skins/content/dark/content.min.css?raw';

export default { ui, content: contentUi + content };
