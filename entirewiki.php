<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Save template feature. Saves entire subwiki contents as an XML template.
 *
 * @copyright &copy; 2007 The Open University
 * @author s.marshall@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ouwiki
 */

require_once(dirname(__FILE__) . '/../../config.php');
require($CFG->dirroot.'/mod/ouwiki/basicpage.php');

$id = required_param('id', PARAM_INT); // Course Module ID
$pagename = optional_param('page', '', PARAM_TEXT);
$filesexist = optional_param('filesexist', 0, PARAM_INT);

$url = new moodle_url('/mod/ouwiki/view.php', array('id' => $id, 'page' => $pagename));
$PAGE->set_url($url);

if ($id) {
    if (!$cm = get_coursemodule_from_id('ouwiki', $id)) {
        print_error('invalidcoursemodule');
    }

    // Checking course instance
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

    if (!$ouwiki = $DB->get_record('ouwiki', array('id' => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

    $PAGE->set_cm($cm);
}
$context = context_module::instance($cm->id);
$PAGE->set_pagelayout('incourse');
require_course_login($course, true, $cm);

$ouwikioutput = $PAGE->get_renderer('mod_ouwiki');

$format = required_param('format', PARAM_ALPHA);
if ($format !== OUWIKI_FORMAT_HTML && $format !== OUWIKI_FORMAT_PDF 
	&& $format !== OUWIKI_FORMAT_TEMPLATE && $format !== OUWIKI_FORMAT_HTML_PRINT) {
    print_error('Unexpected format');
}

// Get basic wiki details for filename
$filename = $course->shortname.'.'.$ouwiki->name;
$filename = preg_replace('/[^A-Za-z0-9.-]/' , '_', $filename);

$markup = '';
$fs = null;

switch ($format) {
    case OUWIKI_FORMAT_TEMPLATE:
        $markup = '<wiki>';
        $files = array();
        $fs = get_file_storage();
        break;
    case OUWIKI_FORMAT_HTML_PRINT:
    	
    	$url_object_array = $PAGE->theme->css_urls($PAGE);
    	$url_object = $url_object_array[0];
    	$css_url = $url_object->out();
    	 
    	$markup = '<html>';
    	$markup .= '<head>';
    	$markup .= '<link rel="stylesheet" href="'.$css_url.'">';
    	$markup .= '</head>';
    	$markup .= '<body>';
    	
    	break;
    case OUWIKI_FORMAT_PDF:

    	$markup = '<html>';
        $css = file_get_contents(dirname(__FILE__) .'/pdf.css');
        $markup .= '<head>';
        $markup .= '<style>' . $css . '</style>'; 
        $markup .= '</head>';
        $markup .= '<body>';
                
        break;
    case OUWIKI_FORMAT_HTML:
        // Do header
        echo $ouwikioutput->ouwiki_print_start($ouwiki, $cm, $course, $subwiki, get_string('entirewiki', 'ouwiki'), $context, null, false, true);
        print '<div class="ouwiki_content">';
        break;
}

// Get list of all pages.
$first = true;
$index = ouwiki_get_subwiki_index($subwiki->id);
$brokenimagestr = get_string('brokenimage', 'ouwiki');

$orphans = false;
$treemode = optional_param('type', '', PARAM_ALPHA) == 'tree';

// Check for orphan posts.
foreach ($index as $indexitem) {
    if (count($indexitem->linksfrom) == 0 && $indexitem->title !== '') {
        $orphans = true;
        break;
    }
}

// // Original
// if (($treemode) && ($format == OUWIKI_FORMAT_HTML) ) {
//     ouwiki_build_tree($index);
//     // Print out in hierarchical form...
//     print '<ul class="ouw_indextree">';
//     $functionname = 'ouwiki_display_entirewiki_page_in_index';
//     print ouwiki_tree_index($functionname, reset($index)->pageid, $index, $subwiki, $cm, $context);
//     print '</ul>';

//     if ($orphans) {
//         print '<h2 class="ouw_orphans">'.get_string('orphanpages', 'ouwiki').'</h2>';
//         print '<ul class="ouw_indextree">';
//         foreach ($index as $indexitem) {
//             if (count($indexitem->linksfrom) == 0 && $indexitem->title !== '') {
//                 $orphanindex = ouwiki_get_sub_tree_from_index($indexitem->pageid, $index);
//                 ouwiki_build_tree($orphanindex);
//                 print ouwiki_tree_index($functionname, $indexitem->pageid, $orphanindex, $subwiki, $cm, $context);
//             }
//         }
//         print '</ul>';
//     }
// } else {


//If tree view specified.
if (($treemode) && ($format == OUWIKI_FORMAT_HTML || $format == OUWIKI_FORMAT_PDF || $format == OUWIKI_FORMAT_HTML_PRINT) ) {

    ouwiki_build_tree($index);
    // Print out in hierarchical form...

    $treeOutput = '<ul class="ouw_indextree">';

    $functionname = 'ouwiki_display_entirewiki_page_in_index';
    $treeOutput .= ouwiki_tree_index($functionname, reset($index)->pageid, $index, $subwiki, $cm, $context);
    $treeOutput .= '</ul>';

    if ($orphans) {
        $treeOutput .= '<h2 class="ouw_orphans">'.get_string('orphanpages', 'ouwiki').'</h2>';
        $treeOutput .= '<ul class="ouw_indextree">';
        foreach ($index as $indexitem) {
            if (count($indexitem->linksfrom) == 0 && $indexitem->title !== '') {
                $orphanindex = ouwiki_get_sub_tree_from_index($indexitem->pageid, $index);
                ouwiki_build_tree($orphanindex);
                $treeOutput .= ouwiki_tree_index($functionname, $indexitem->pageid, $orphanindex, $subwiki, $cm, $context);
            }
        }
        $treeOutput .= '</ul>';
    }

    if($format == OUWIKI_FORMAT_HTML)
        print $treeOutput;

    if($format == OUWIKI_FORMAT_PDF || $format == OUWIKI_FORMAT_HTML_PRINT)
        $markup .= $treeOutput;

} else {
    foreach ($index as $pageinfo) {
        if (count($pageinfo->linksfrom)!= 0 || $pageinfo->title === '') {
            // Get page details.
            $pageversion = ouwiki_get_current_page($subwiki, $pageinfo->title);
            // If the page hasn't really been created yet, skip it.
            if (is_null($pageversion->xhtml)) {
                continue;
            }

            
            $output = get_online_display_content($format, $pageversion, $context, $subwiki, $cm, $index, $fs, $files);
			
            if($format == OUWIKI_FORMAT_HTML)
            	print $output;
            
           	if($format == OUWIKI_FORMAT_PDF || $format == OUWIKI_FORMAT_HTML_PRINT)
           		$markup .= $output;
            
            if ($first) {
                $first = false;
            }
        }
    }

    if ($orphans) {
        if ($format == OUWIKI_FORMAT_HTML) {
            print '<h2 class="ouw_orphans">'.get_string('orphanpages', 'ouwiki').'</h2>';
        } else if ($format != OUWIKI_FORMAT_TEMPLATE) {
            $markup .= '<h2 class="ouw_orphans">'.get_string('orphanpages', 'ouwiki').'</h2>';
        }

        foreach ($index as $indexitem) {
            if (count($indexitem->linksfrom) == 0 && $indexitem->title !== '') {
                // Get page details.
                $pageversion = ouwiki_get_current_page($subwiki, $indexitem->title);
                // If the page hasn't really been created yet, skip it.
                if (is_null($pageversion->xhtml)) {
                    continue;
                }

                $markup .= get_online_display_content($format, $pageversion, $context, $subwiki, $cm, $index, $fs, $files);

                if ($first) {
                    $first = false;
                }

            }
        }
    }
}

switch ($format) {
    case OUWIKI_FORMAT_TEMPLATE:
        $markup .= '</wiki>';
        // Create temp xml file.
        $filerec = new stdClass();
        $filerec->contextid = $context->id;
        $filerec->component = 'mod_ouwiki';
        $filerec->filearea = 'temp';
        $filerec->filepath = '/';
        $filerec->itemid = $id;
        $filerec->filename = strtolower(get_string('template', 'mod_ouwiki')) . '.xml';
        $files[$filerec->filename] = $fs->create_file_from_string($filerec, $markup);
        $zip = get_file_packer();
        $file = $zip->archive_to_storage($files, $context->id, 'mod_ouwiki', 'temp', $id, '/', $filename . '.zip');
        send_stored_file($file, 0, 0, true, array('dontdie' => true));
        // Delete all our temp files used in this process.
        $fs->delete_area_files($context->id, 'mod_ouwiki', 'temp', $id);
        exit;
        break;

    case OUWIKI_FORMAT_HTML_PRINT:
    	
    	$markup .= '</body></html>';
    	
    	echo $markup;
    	break;
    	 
    case OUWIKI_FORMAT_PDF:
        $markup .= '</body></html>';
        
        require_once($CFG->libdir . '/pdflib.php');
        
        $doc = new pdf;
        $doc->setFont('helvetica');
        $doc->setPrintHeader(false);
        $doc->setPrintFooter(false);
        $doc->AddPage();
        $doc->writeHTML($markup);
        $doc->Output();
        
        break;

    case OUWIKI_FORMAT_HTML:
        ouwiki_print_footer($course, $cm, $subwiki);
        break;
}

function get_online_display_content($format, $pageversion, $context, $subwiki, $cm, $index, $fs, &$files) {
    $markup = '';
    $visibletitle = $pageversion->title === '' ? get_string('startpage', 'ouwiki') : $pageversion->title;

    if ($format != OUWIKI_FORMAT_TEMPLATE) {
        $pageversion->xhtml = file_rewrite_pluginfile_urls($pageversion->xhtml, 'pluginfile.php',
                $context->id, 'mod_ouwiki', 'content', $pageversion->versionid);
    }

    switch ($format) {
        case OUWIKI_FORMAT_TEMPLATE:
            // Print template wiki page.
            $markup .= '<page>';
            if ($pageversion->title !== '') {
                $markup .= '<title>' . htmlspecialchars($pageversion->title) . '</title>';
            }
            $markup .= '<versionid>' . $pageversion->versionid . '</versionid>';
            // Copy images found in content.
            preg_match_all('#<img.*?src="@@PLUGINFILE@@/(.*?)".*?/>#', $pageversion->xhtml, $matches);
            if (! empty($matches)) {
                // Extract the file names from the matches.
                foreach ($matches[1] as $key => $match) {
                    // Get file name and copy to zip.
                    $match = urldecode($match);
                    // Copy image - on fail swap tag with string.
                    if ($file = $fs->get_file($context->id, 'mod_ouwiki', 'content',
                            $pageversion->versionid, '/', $match)) {
                        $files["/$pageversion->versionid/$match/"] = $file;
                    } else {
                        $pageversion->xhtml = str_replace($matches[0][$key], $brokenimagestr,
                                $pageversion->xhtml);
                    }
                }
            }
            $markup .= '<xhtml>' . htmlspecialchars($pageversion->xhtml) . '</xhtml>';
            // Add attachments.
            if ($attachments = $fs->get_area_files($context->id, 'mod_ouwiki', 'attachment',
                    $pageversion->versionid, 'itemid', false)) {
                // We have attachements.
                $markup .= '<attachments>';
                $attachmentsarray = array();
                foreach ($attachments as $attachment) {
                    $filename = $attachment->get_filename();
                    array_push($attachmentsarray, $filename);
                    $files["/$pageversion->versionid/$filename/"] = $attachment;
                }
                $markup .= implode('|', $attachmentsarray);
                $markup .= '</attachments>';
            }
            $markup .= '</page>';
            break;
        case OUWIKI_FORMAT_PDF || OUWIKI_FORMAT_HTML_PRINT:
            //$markup .= '<h1>' . htmlspecialchars($visibletitle) . '</h1>';
            //$markup .= trim($pageversion->xhtml);
            //$markup .= '<br /><br /><hr />';

            $markup .= '<div class="ouw_entry"><a name="' . $pageversion->pageid . '"></a><h1 class="ouw_entry_heading">' .
                    '<a href="view.php?' . ouwiki_display_wiki_parameters($pageversion->title, $subwiki, $cm) .
                    '">' . htmlspecialchars($visibletitle) . '</a></h1>';
            $markup .= ouwiki_convert_content($pageversion->xhtml, $subwiki, $cm, $index, $pageversion->xhtmlformat);
            $markup .= '</div>';

            break;
        case OUWIKI_FORMAT_HTML:
            print '<div class="ouw_entry"><a name="' . $pageversion->pageid . '"></a><h1 class="ouw_entry_heading">' .
                    '<a href="view.php?' . ouwiki_display_wiki_parameters($pageversion->title, $subwiki, $cm) .
                    '">' . htmlspecialchars($visibletitle) . '</a></h1>';
            print ouwiki_convert_content($pageversion->xhtml, $subwiki, $cm, $index, $pageversion->xhtmlformat);
            print '</div>';
            break;
    }

    return $markup;

}
