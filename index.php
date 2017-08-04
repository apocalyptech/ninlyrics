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

    // Default to requiring 2 songs for phrases; also if
    // we don't have min_songs in $_REQUEST, we're on
    // the "main" page; so default to about.
    if (!array_key_exists('min_songs', $_REQUEST))
    {
        $_REQUEST['min_songs'] = 2;
        $action = 'about';
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

function html_text($name, $size=1, $maxlength=null, $onChange=null)
{
    print '<input type="text" name="' . $name . '" id="' . $name . '" size="' . $size . '"';
    if (!is_null($maxlength))
    {
        print ' maxlength="' . $maxlength . '"';
    }
    if (!is_null($onChange))
    {
        print ' onChange="' . $onChange . '"';
    }
    if (array_key_exists($name, $_REQUEST))
    {
        print ' value="' . htmlentities($_REQUEST[$name]) . '"';
    }
    print ">\n";
}

function html_checkbox($name, $onChange=null)
{
    print '<input type="checkbox" name="' . $name . '" id="' . $name . '"';
    if (!is_null($onChange))
    {
        print ' onChange="' . $onChange . '"';
    }
    if (array_key_exists($name, $_REQUEST))
    {
        print ' checked';
    }
    print ">\n";
}

function html_select($name, $data, $id_var, $text_var, $multiple=false, $on_change='')
{
    print '<select name="' . $name;
    if ($multiple)
    {
        print '[]';
    }
    print '" id="' . $name . '" size="' . count($data) . '"';
    if ($multiple)
    {
        print ' multiple';
    }
    if ($on_change != '')
    {
        print ' onChange="' . $on_change . '"';
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
    $phrase = trim($_REQUEST['phrase']);

    // Trim off any non-word chars from beginning or end; those would screw up
    // our \b matching for highlighting.
    $regex_phrase = preg_replace('/^\W*(.*?)\W*$/', '\1', $phrase);

    print "<div class=\"content\">\n";
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
            print preg_replace('/\b(' . $regex_phrase . ')\b/i', '<span class="hiphrase">\1</span>', $data['lyrics']);
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
    print "</div>\n";
}

function do_search_box()
{
    $albums = data_get_albums();
    ?>
    <div class="searchterms">
    <div class="searchtitle">Search for Phrases</div>
    <div class="searchnote">Note: text searches will match on substrings.<br>Minimum text search length is 3.</div>
    <form method="GET" action="index.php" onSubmit="enableAlbumsRestrict();">
    <table>
    <tr>
    <th>Contains:</th>
    <td colspan="2"><?php html_text('text', 25); ?></td>
    </tr>
    <tr>
    <th><nobr># Words:</nobr></th>
    <td>At least: <?php html_text('min_words'); ?></td>
    <td>At most: <?php html_text('max_words'); ?></td>
    </tr>
    <tr>
    <th><nobr># Albums:</nobr></th>
    <td id="noresults_1">At least: <?php html_text('min_albums', 1, null, 'checkAlbumsRestrict();'); ?></td>
    <td>At most: <?php html_text('max_albums'); ?></td>
    </tr>
    <tr>
    <th><nobr># Songs:</nobr></th>
    <td>At least: <?php html_text('min_songs'); ?></td>
    <td>At most: <?php html_text('max_songs'); ?></td>
    </tr>
    <tr>
    <th id="noresults_2" class="searchtop"><nobr>Only Albums:</nobr></th>
    <td colspan="2"><?php html_select('albums', $albums, 'aid', 'atitle', true, 'checkAlbumsRestrict();'); ?></td>
    </tr>
    <tr style="disabled: true;">
    <td>&nbsp;</td>
    <td colspan="2" id="restrict_cell">Limit "<b># Albums</b>" and "<b># Songs</b>" to selected albums?
    <?php html_checkbox('albums_restrict', 'checkAlbumsRestrict();'); ?></td>
    </tr>
    <tr id="no_results_text" class="no_results no_results_text_off">
    <td>&nbsp;</td>
    <td colspan="2"><hr>Note: with the currently-selected search terms, no results will be returned.<hr></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td colspan="2"><input type="submit" value="Search"></td>
    </tr>
    <tr>
    <td>&nbsp;</td>
    <td colspan="2"><input type="button" value="Reset to Defaults" onClick="document.location='index.php?text=&min_words=&max_words=&min_albums=&max_albums=&min_songs=2&max_songs=&albums_restrict=on';"></td>
    </tr>
    </table>
    </form>
    <p><a href="index.php">Docs / Information</a></p>
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
                $intval = (int)$_REQUEST[$searchint];
                if ($intval > 0)
                {
                    $constraints[$searchint] = (int)$_REQUEST[$searchint];
                    array_push($constraints_eng, $eng . ': ' . (int)$_REQUEST[$searchint]);
                }
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
    print "<div class=\"content\">\n";
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
    print "</div>\n";
}

function do_about()
{
    ?>
    <h2>Interesting Results</h2>

    <p>
    These were noted in August 2017, btw, after the release of ADD VIOLENCE.
    Future releases (or updates to our lyric database) may, of course, change
    this around somewhat.  I'll endeavor to keep this up to date as the DB
    changes, though.
    </p>

    <ul>
    <li>
    The
    <a href="index.php?text=&min_words=6&max_words=&min_albums=2&max_albums=&min_songs=&max_songs=&albums_restrict=on">longest phrases occurring in more than one album</a>
    are six words long:
    "<a href="index.php?text=&min_words=6&max_words=&min_albums=2&max_albums=&min_songs=&max_songs=&albums_restrict=on&phrase=i'm+on+my+hands+and+knees">i'm on my hands and knees</a>,"
    "<a href="index.php?text=&min_words=6&max_words=&min_albums=2&max_albums=&min_songs=&max_songs=&albums_restrict=on&phrase=just+for+the+fuck+of+it">just for the fuck of it</a>,"
    and "<a href="index.php?text=&min_words=6&max_words=&min_albums=2&max_albums=&min_songs=&max_songs=&albums_restrict=on&phrase=of+who+i+used+to+be">of who I used to be</a>."
    </li>
    <li>The ubiquitous "<a href="index.php?text=&min_words=5&max_words=&min_albums=2&max_albums=&min_songs=&max_songs=&albums_restrict=on&phrase=nothing+can+stop+me+now">nothing can stop me now</a>"
    can be found in five songs across three albums.</li>
    <li>When we drop down to three-word phrases,
    "<a href="index.php?text=&min_words=3&max_words=&min_albums=2&max_albums=&min_songs=&max_songs=&albums_restrict=on&phrase=i+don%27t+know">I don't know</a>"
    tops the bunch, appearing in ten songs across six albums.
    "<a href="index.php?text=&min_words=3&max_words=&min_albums=2&max_albums=&min_songs=&max_songs=&albums_restrict=on&phrase=used+to+be">used to be</a>"
    gets pretty close: ten songs, but only across five albums.</li>
    <li>For all of TR's lyrical self-absorbtion,
    "<a href="index.php?min_songs=2&albums_restrict=1&phrase=i">I</a>" only appears in 96 songs,
    compared to 98 for "<a href="index.php?min_songs=2&albums_restrict=1&phrase=you">you</a>".
    </li>
    <li>Surprisingly,
    "<a href="index.php?text=starfuckers&min_words=&max_words=&min_albums=&max_albums=&min_songs=&max_songs=&albums_restrict=on">starfuckers</a>"
    only appears in a single track!</li>
    </ul>

    <p>Let me know if you think anything else deserves to be noted in here.</p>

    <h2>Songs In Database</h2>

    <p>
    This database is primarily concerned with the "main" NIN releases, though I've
    included various B-sides and extra content where it seemed appropriate.  I've
    purposefully excluded any covers from here, so song like <em>Get Down Make Love</em>,
    <em>Memorabilia</em>, and <em>Metal</em> aren't present.  <em>Suck</em> is a corner
    case I remain a little undecided on, since that was originally Pigface but TR
    obviously had some input into it.  So far I've left that one out.  I've also
    intentionally excluded the handful of early demo songs which never made it to
    PHM, like <em>Purest Feeling</em>, since TR's effectively disavowed those.  I've
    also so far excluded the various "reworked" remixes such as <em>Closer To God</em>
    which technically add extra lyrics but are largely the same as their source
    material.
    </p>

    <p>
    One question I've struggled with is the inclusion of lyrics printed in the
    official lyric sheets which aren't actually present in the songs themselves.
    There's a bit of that in PHM, and a <em>lot</em> of it in Not The Actual
    Events.  I've excluded most of the "extra" lyrics though I'm sure a
    few have slipped through.  There's also occasional minor differences in the
    printed lyrics and what's actually sung; I've tended to keep with the
    printed version in those cases.  I've also left in the English translation
    of the spoken bits in <em>La Mer</em>.  It's perhaps worth noting that I
    believe the lyrics were largely copy+pasted from <a href="http://nin.wiki">nin.wiki</a>
    back in 2014, rather than copied by hand, so I've not actually manually
    checked any of these versus the printed sheets.  They've been edited for
    formatting, though, so they can be imported more usefully into this app.
    </p>

    <p>
    I've currently lumped in <em>The Perfect Drug</em> into "The Fragile (and related)"
    since it appeared on one of the <em>We're In This Together</em> EPs.  It didn't
    really seem right to have it out on its own.
    </p>

    <p>
    If you've got any arguments for doing things differently in here which you
    think could sway my current decisions, definitely feel free to get in contact.
    The source lyric textfiles used in the database are available
    <a href="https://github.com/apocalyptech/ninlyrics/tree/master/import/albums">here
    on Github</a>.
    </p>

    <ul>
    <li><strong>Pretty Hate Machine</strong>
        <ul>
        <li>Head Like a Hole</li>
        <li>Terrible Lie</li>
        <li>Down In It</li>
        <li>Sanctified</li>
        <li>Something I Can Never Have</li>
        <li>Kinda I Want To</li>
        <li>Sin</li>
        <li>That's What I Get</li>
        <li>The Only Time</li>
        <li>Ringfinger</li>
        </ul>
        </li>
    <li><strong>Broken</strong>
        <ul>
        <li>Wish</li>
        <li>Last</li>
        <li>Happiness in Slavery</li>
        <li>Gave Up</li>
        </ul>
        </li>
    <li><strong>The Downward Spiral</strong>
        <ul>
        <li>Mr. Self Destruct</li>
        <li>Piggy</li>
        <li>Heresy</li>
        <li>March of the Pigs</li>
        <li>Closer</li>
        <li>Ruiner</li>
        <li>The Becoming</li>
        <li>I Do Not Want This</li>
        <li>Big Man With a Gun</li>
        <li>Eraser</li>
        <li>Reptile</li>
        <li>The Downward Spiral</li>
        <li>Hurt</li>
        </ul>
        </li>
    <li><strong>The Fragile (and related)</strong>
        <ul>
        <li>Somewhat Damaged</li>
        <li>The Day The World Went Away</li>
        <li>The Wretched</li>
        <li>We're In This Together</li>
        <li>The Fragile</li>
        <li>Even Deeper</li>
        <li>No, You Don't</li>
        <li>La Mer <em>(English translation)</em></li>
        <li>The Great Below</li>
        <li>Into the Void</li>
        <li>Where is Everybody?</li>
        <li>Please</li>
        <li>Starfuckers, Inc.</li>
        <li>I'm Looking Forward to Joining You, Finally</li>
        <li>The Big Come Down</li>
        <li>Underneath It All</li>
        <li>10 Miles High</li>
        <li>The New Flesh</li>
        <li>And All That Could Have Been</li>
        <li>Deep</li>
        <li>The Perfect Drug</li>
        </ul>
        </li>
    <li><strong>With Teeth (and related)</strong>
        <ul>
        <li>All The Love In The World</li>
        <li>You Know What You Are?</li>
        <li>The Collector</li>
        <li>The Hand That Feeds</li>
        <li>Love Is Not Enough</li>
        <li>Every Day Is Exactly The Same</li>
        <li>With Teeth</li>
        <li>Only</li>
        <li>Getting Smaller</li>
        <li>Sunspots</li>
        <li>The Line Begins to Blur</li>
        <li>Beside You In Time</li>
        <li>Right Where It Belongs</li>
        <li>Home</li>
        <li>Non-Entity</li>
        <li>Not So Pretty Now</li>
        </ul>
        </li>
    <li><strong>Year Zero</strong>
        <ul>
        <li>The Beginning of the End</li>
        <li>Survivalism</li>
        <li>The Good Soldier</li>
        <li>Vessel</li>
        <li>Me, I'm Not</li>
        <li>Capital G</li>
        <li>My Violent Heart</li>
        <li>The Warning</li>
        <li>God Given</li>
        <li>Meet Your Master</li>
        <li>The Greater Good</li>
        <li>The Great Destroyer</li>
        <li>In This Twilight</li>
        <li>Zero Sum</li>
        </ul>
        </li>
    <li><strong>The Slip</strong>
        <ul>
        <li>1,000,000</li>
        <li>Letting You</li>
        <li>Discipline</li>
        <li>Echoplex</li>
        <li>Head Down</li>
        <li>Lights in the Sky</li>
        <li>Demon Seed</li>
        </ul>
        </li>
    <li><strong>Hesitation Marks</strong>
        <ul>
        <li>Copy of A</li>
        <li>Came Back Haunted</li>
        <li>Find My Way</li>
        <li>All Time Low</li>
        <li>Disappointed</li>
        <li>Everything</li>
        <li>Satellite</li>
        <li>Various Methods of Escape</li>
        <li>Running</li>
        <li>I Would For You</li>
        <li>In Two</li>
        <li>While I'm Still Here</li>
        </ul>
        </li>
    <li><strong>Not The Actual Trilogy</strong> <em>(Not The Actual Events, ADD VIOLENCE, ...)</em>
        <ul>
        <li>Branches/Bones</li>
        <li>Dear World,</li>
        <li>She's Gone Away</li>
        <li>The Idea of You</li>
        <li>Burning Bright (Field on Fire)</li>
        <li>Less Than</li>
        <li>The Lovers</li>
        <li>This Isn't the Place</li>
        <li>Not Anymore</li>
        <li>The Background World</li>
        </ul>
        </li>
    </ul>
    <?php
}

?><!DOCTYPE HTML>
<html>
<head>
<title>NIN Lyrics</title>
<link rel="stylesheet" type="text/css" media="all" href="main.css">
<script type="text/javascript" src="func.js"></script>
</head>
<body onLoad="checkAlbumsRestrict();">
<?php

if ($action != 'nothing')
{
    do_search_box();
}

?>
<h1>Nine Inch Nails Lyrics Stats</h1>
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
        do_phrase();
        break;

    case 'about':
        do_about();
        break;

    case 'search':
    default:
        do_search();
        break;

}

?>
</body>
</html>
