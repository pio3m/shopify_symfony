/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
// Importuj Bootstrap
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';

import tinymce from 'tinymce';

// Opcjonalnie, zaimportuj motyw oraz wtyczki TinyMCE
import 'tinymce/themes/silver/theme';
import 'tinymce/icons/default/icons';

// Import wtyczek (jeśli potrzebujesz)
import 'tinymce/plugins/link';
import 'tinymce/plugins/table';
import 'tinymce/plugins/image'