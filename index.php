<?php // vim: set expandtab tabstop=4 shiftwidth=4: 

$errors = array();
$action = 'search';

require_once('data.php');

if (data_have_db())
{
    if (array_key_exists('phrase', $_REQUEST))
    {
        $action = 'phrase';
    }

    // Default to requiring 2 songs for phrases
    if (!array_key_exists('min_songs', $_REQUEST))
    {
        $_REQUEST['min_songs'] = 2;
    }
}
else
{
    $action = 'nothing';
    array_push($errors, 'Unable to get database connection');
}

// Set our default for albums_restrict checkbox.
if (!array_key_exists('min_words', $_REQUEST))
{
    $_REQUEST['albums_restrict'] = true;
}

function html_text($name, $size=1, $maxlength=null)
{
    print '<input type="text" name="' . $name . '" id="' . $name . '" size="' . $size . '"';
    if (!is_null($maxlength))
    {
        print ' maxlength="' . $maxlength . '"';
    }
    if (array_key_exists($name, $_REQUEST))
    {
        print ' value="' . htmlentities($_REQUEST[$name]) . '"';
    }
    print ">\n";
}

function html_checkbox($name)
{
    print '<input type="checkbox" name="' . $name . '" id="' . $name . '"';
    if (array_key_exists($name, $_REQUEST))
    {
        print ' checked';
    }
    print ">\n";
}

function html_select($name, $data, $id_var, $text_var, $multiple=false)
{
    print '<select name="' . $name;
    if ($multiple)
    {
        print '[]';
    }
    print '" size="' . count($data) . '"';
    if ($multiple)
    {
        print ' multiple';
    }
    print ">\n";
    foreach ($data as $item)
    {
        print '<option value="' . $item[$id_var] . '"';
        if (array_key_exists($name, $_REQUEST))
        {
            if ($multiple and is_array($_REQUEST[$name]))
            {
                $_REQUEST[$name] = array_map('intval', $_REQUEST[$name]);
                if (in_array($item[$id_var], $_REQUEST[$name]))
                {
                    print ' selected';
                }
            }
            else
            {
                if ($item[$id_var] == (int)$_REQUEST[$name])
                {
                    print ' selected';
                }
            }
        }
        print '>' . htmlentities($item[$text_var]) . "</option>\n";
    }
    print "</select>\n";
}

function html_header_sort($cursort, $sortname, $label, $class=null)
{
    // We're always at least in the 'sortheader' class
    if (is_null($class))
    {
        $class = 'sortheader';
    }
    else
    {
        $class .= ' sortheader';
    }

    // Some visual indication of this header being the one that's being used to sort
    if ($cursort == $sortname . '_up' or $cursort == $sortname . '_down')
    {
        $class .= ' current_sort';
    }
   
    print '<td class="' . $class. '">';
    if ($cursort == $sortname . '_up')
    {
        print '&uarr;';
    }
    else
    {
        print '<a href="index.php?' . modify_querystring('sort', $sortname . '_up') .'">&uarr;</a>';
    }
    print '&nbsp;' . htmlentities($label) . '&nbsp;';
    if ($cursort == $sortname . '_down')
    {
        print '&darr;';
    }
    else
    {
        print '<a href="index.php?' . modify_querystring('sort', $sortname . '_down') .'">&darr;</a>';
    }
    print "</td>\n";
}

function modify_querystring($name, $val)
{
    $qs = array();
    foreach ($_REQUEST as $reqkey => $reqval)
    {
        $qs[$reqkey] = $reqval;
    }
    $qs[$name] = $val;
    $qs_vars = array();
    foreach ($qs as $key => $val)
    {
        if (is_array($val))
        {
            foreach ($val as $inner_val)
            {
                array_push($qs_vars, urlencode($key . '[]') . '=' . urlencode($inner_val));
            }
        }
        else
        {
            array_push($qs_vars, urlencode($key) . '=' . urlencode($val));
        }
    }
    return implode('&amp;', $qs_vars);
}

function do_phrase()
{
    $phrase = preg_replace('/[^a-zA-Z\' ]/', '', trim($_REQUEST['phrase']));
    $songlist = data_get_songlist($phrase);
    if (count($songlist) > 0)
    {
        print '<p><strong>Phrase:</strong> <em>"' . htmlentities($phrase) . "\"</em></p>\n";
        print "<table class=\"songs\">\n";
        print "<tr>\n";
        print "<th>Song</th>\n";
        print "<th>Album</th>\n";
        print "</tr>\n";
        $i=0;
        foreach ($songlist as $data)
        {
            $i++;
            print '<tr';
            if ($i % 2 == 0)
            {
                print ' class="evenrow"';
            }
            print ">\n";
            print '<td><a href="#" onClick="toggleLyrics(\'' . $data['sid'] . '\'); return false;">' . htmlentities($data['stitle']) . "</a></td>\n";
            print '<td><nobr><a href="index.php?albums[]=' . $data['aid'] . '">' . htmlentities($data['atitle']) . "</a></nobr></td>\n";
            print "</tr>\n";
            print '<tr class="lyrics" id="lyrics_' . $data['sid'] . "\" style=\"display: none;\">\n";
            print "<td colspan=\"2\">\n";
            print "<blockquote><pre>";
            print preg_replace('/(' . $phrase . ')/i', '<span class="hiphrase">\1</span>', $data['lyrics']);
            print "</pre></blockquote>\n";
            print "</td>\n";
            print "</tr>\n";
        }
        print "</table>\n";
    }
    else
    {
        print '<p>No songs found with phrase "' . htmlentities($phrase) . "\"</p>\n";
    }
}

function do_search_box()
{
    $albums = data_get_albums();
    ?>
    <div class="searchterms">
    <div class="searchtitle">Search for Phrases</div>
    <form method="GET" action="index.php">
    <table>
    <tr>
    <th>Phrase containing:</th>
    <td colspan="2"><?php html_text('text', 25); ?></td>
    </tr>
    <tr>
    <th>Words in Phrase:</th>
    <td>At least: <?php html_text('min_words'); ?></td>
    <td>At most: <?php html_text('max_words'); ?></td>
    </tr>
    <tr>
    <th>Number of Albums:</th>
    <td>At least: <?php html_text('min_albums'); ?></td>
    <td>At most: <?php html_text('max_albums'); ?></td>
    </tr>
    <tr>
    <th>Number of Songs:</th>
    <td>At least: <?php html_text('min_songs'); ?></td>
    <td>At most: <?php html_text('max_songs'); ?></td>
    </tr>
    <tr>
    <th>Only Albums:</th>
    <td colspan="2"><?php html_select('albums', $albums, 'aid', 'atitle', true); ?></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td colspan="2">Restrict "Number of Albums" and "Number of Songs" by selected albums?
    <?php html_checkbox('albums_restrict'); ?></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td colspan="2"><input type="submit" value="Search"></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td colspan="2"><input type="button" value="Reset to Defaults" onClick="document.location='index.php';"></td>
    </tr>
    </table>
    </form>
    </div>
    <?php
}

function do_search()
{
    $albums = data_get_albums();
    $albumids = array();
    foreach ($albums as $album)
    {
        $albumids[$album['aid']] = $album;
    }

    // Read constraints from our $_REQUEST
    $doing_album_limit = false;
    $constraints = array();
    $constraints_eng = array();
    $valid_ints = array(
        'min_words' => 'Minimum Words',
        'max_words' => 'Maximum Words',
        'min_albums' => 'Minimum Albums',
        'max_albums' => 'Maximum Albums',
        'min_songs' => 'Minimum Songs',
        'max_songs' => 'Maximum Songs',
    );
    foreach ($valid_ints as $searchint => $eng)
    {
        if (array_key_exists($searchint, $_REQUEST))
        {
            if (trim($_REQUEST[$searchint]) != '')
            {
                $constraints[$searchint] = (int)$_REQUEST[$searchint];
                array_push($constraints_eng, $eng . ': ' . (int)$_REQUEST[$searchint]);
            }
        }
    }
    if (array_key_exists('text', $_REQUEST))
    {
        $text = trim($_REQUEST['text']);
        if ($text != '' and strlen($text) > 2)
        {
            $constraints['text'] = $text;
            array_push($constraints_eng, 'Phrase Contains: "' . htmlentities($text) .'"');
        }
    }
    if (array_key_exists('albums', $_REQUEST))
    {
        if (!is_array($_REQUEST['albums']))
        {
            $_REQUEST['albums'] = array($_REQUEST['albums']);
        }
        $albumlist = array();
        $albumlist_eng = array();
        foreach ($_REQUEST['albums'] as $albumid)
        {
            if (array_key_exists((int)$albumid, $albumids))
            {
                $albumlist[(int)$albumid] = true;
            }
        }
        foreach (array_keys($albumlist) as $albumid)
        {
            array_push($albumlist_eng, $albumids[$albumid]['atitle']);
        }
        if (count($albumlist) > 0 and count($albumlist) < count($albums))
        {
            $doing_album_limit = true;
            $constraints['albums'] = array_keys($albumlist);
            if (count($albumlist) > 1)
            {
                $plural = 's';
            }
            else
            {
                $plural = '';
            }
            array_push($constraints_eng, 'In Album' . $plural . ': ' . implode(', ', $albumlist_eng));
        }
    }
    if (array_key_exists('albums_restrict', $_REQUEST))
    {
        $constraints['albums_restrict'] = true;
    }
    else
    {
        $constraints['albums_restrict'] = false;
    }

    // Read sort value from our $_REQUEST
    $sort = 'songcount_down';
    $valid_sorts = array(
        'phrase_up', 'phrase_down',
        'songcount_up', 'songcount_down',
        'albumcount_up', 'albumcount_down',
    );
    if ($doing_album_limit)
    {
        $sort = 'songcount_q_down';
        array_push($valid_sorts, 'songcount_q_up');
        array_push($valid_sorts, 'songcount_q_down');
        array_push($valid_sorts, 'albumcount_q_up');
        array_push($valid_sorts, 'albumcount_q_down');
    }
    if (array_key_exists('sort', $_REQUEST))
    {
        if (in_array($_REQUEST['sort'], $valid_sorts))
        {
            $sort = $_REQUEST['sort'];
        }
    }

    // Display our search terms
    if (count($constraints_eng) > 0)
    {
        ?>
        <div class="constraints">
        <strong>Search constraints:</strong>
        <ul>
        <?php
        foreach ($constraints_eng as $eng)
        {
            print '<li>' . $eng . "</li>\n";
        }
        ?>
        </ul>
        </div>
        <?php
    }

    // Figure out if we're paging
    $pagesize = 100;
    if (array_key_exists('startat', $_REQUEST))
    {
        $startat = (int)$_REQUEST['startat'];
        if ($startat < 0)
        {
            $startat = 0;
        }
    }
    else
    {
        $startat = 0;
    }

    $count = 0;
    $phrases = data_do_search($constraints, $sort, $count, $pagesize, $startat);
    if ($count == 1)
    {
        $plural = '';
    }
    else
    {
        $plural = 's';
    }
    $colcount = 3;
    if ($doing_album_limit)
    {
        $colcount += 2;
    }
    if (count($phrases) > 0)
    {
        print "<div class=\"phrasesdiv\">\n";
        print "<table class=\"phrases\">\n";
        print "<tr>\n";
        print '<td colspan="' . $colcount . "\">\n";
        if ($startat + $pagesize > $count)
        {
            $endcount = $count;
        }
        else
        {
            $endcount = $startat + $pagesize;
        }
        printf("<div class=\"recordcount\">Showing %d-%d of %d phrase%s</div>\n", $startat+1, $endcount, $count, $plural);
        print "</td>\n";
        print "</tr>\n";
        $pager_row = '';
        if ($count > $pagesize)
        {
            $pager_arr = array();
            $pager_arr[] = "<tr>\n";
            if ($startat > 0)
            {
                $newstart = $startat - $pagesize;
                if ($newstart < 0)
                {
                    $newstart = 0;
                }
                $pager_arr[] = '<td class="prevrecords">';
                $pager_arr[] = '<a href="index.php?' . modify_querystring('startat', 0) . '">|--</a>';
                $pager_arr[] = '&nbsp;&nbsp;';
                $pager_arr[] = '<a href="index.php?' . modify_querystring('startat', $newstart) . '">&lt;--</a>';
                $pager_arr[] = "</td>\n";
            }
            else
            {
                $pager_arr[] = "<td>&nbsp;</td>\n";
            }
            $pager_arr[] =  '<td colspan="' . ($colcount-2) . "\">&nbsp;</td>\n";
            if ($startat + $pagesize < $count)
            {
                $maxend = intval($count / $pagesize) * $pagesize;
                $pager_arr[] = '<td class="nextrecords">';
                $pager_arr[] = '<a href="index.php?' . modify_querystring('startat', $startat + $pagesize) . '">--&gt;</a>';
                $pager_arr[] = '&nbsp;&nbsp;';
                $pager_arr[] = '<a href="index.php?' . modify_querystring('startat', $maxend) . '">--|</a>';
                $pager_arr[] = "</td>\n";
            }
            else
            {
                $pager_arr[] =  "<td>&nbsp;</td>\n";
            }
            $pager_arr[] = "</tr>\n";
            $pager_row = implode('', $pager_arr);
        }
        print $pager_row;
        if ($doing_album_limit)
        {
            print "<tr>\n";
            print "<td class=\"sortheader header_phrase\">&nbsp;</td>\n";
            print "<td colspan=\"2\" class=\"sortheader header_album\">(in searched albums)</td>\n";
            print "<td colspan=\"2\" class=\"sortheader header_total\">(total)</td>\n";
            print "</tr>\n";
        }
        print "<tr>\n";
        html_header_sort($sort, 'phrase', 'Phrase', 'header_phrase');
        if ($doing_album_limit)
        {
            html_header_sort($sort, 'songcount_q', '# Songs', 'header_album header_number');
            html_header_sort($sort, 'albumcount_q', '# Albums', 'header_album header_number');
        }
        html_header_sort($sort, 'songcount', '# Songs', 'header_total header_number');
        html_header_sort($sort, 'albumcount', '# Albums', 'header_total header_number');
        print "</tr>\n";
        $i = 0;
        foreach ($phrases as $data)
        {
            $i++;
            print '<tr';
            if ($i % 2 == 0)
            {
                print ' class="evenrow"';
            }
            print ">\n";
            print '<td><a href="index.php?' . modify_querystring('phrase', $data['phrase']) . "\">" . htmlentities($data['phrase']) . "</a></td>\n";
            if ($doing_album_limit)
            {
                print '<td>' . $data['songcount_q'] . "</td>\n";
                print '<td>' . $data['albumcount_q'] . "</td>\n";
            }
            print '<td>' . $data['songcount'] . "</td>\n";
            print '<td>' . $data['albumcount'] . "</td>\n";
            print "</tr>\n";
        }
        print $pager_row;
        print "</table>\n";
        print "</div>\n";
    }
    else
    {
        print "<p>No phrases found.</p>\n";
    }
}

?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"> 
<html>
<head>
<title>NIN Lyrics</title>
<link rel="stylesheet" type="text/css" media="all" href="main.css">
<script type="text/javascript" src="func.js"></script>
</head>
<body>
<?php
if (count($errors) > 0)
{
    print "<div class=\"errors\">\n";
    print "<ul>\n";
    foreach ($errors as $error)
    {
        print '<li>' . $error . "</li>\n";
    }
    print "</ul>\n";
    print "</div>\n";
}

switch ($action)
{
    case 'nothing':
        break;

    case 'phrase':
        do_search_box();
        do_phrase();
        break;

    case 'search':
    default:
        do_search_box();
        do_search();
        break;
}

?>
</body>
</html>
