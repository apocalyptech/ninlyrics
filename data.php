<?php // vim: set expandtab tabstop=4 shiftwidth=4:

// Read in our DB values and connect to the database
$dbh = null;
if (file_exists('db-config.php'))
{
    require_once('db-config.php');
    if (isset($dbhost) and
        isset($dbname) and
        isset($dbuser) and
        isset($dbpass))
    {
        $dbconnstr = sprintf('mysql:host=%s;dbname=%s', $dbhost, $dbname);
        try
        {
            $dbh = new PDO($dbconnstr, $dbuser, $dbpass);
        }
        catch (PDOException $e)
        {
        }
    }
}

/**
 * Check to see if we have a database connection
 *
 * @return True or False
 */
function data_have_db()
{
    global $dbh;
    if ($dbh)
    {
        return true;
    }
    else
    {
        return false;
    }
}

/**
 * Retreives a list of albums
 *
 * @return An array of associative arrays.  Each item will have the keys
 *         aid, atitle, and year.
 */
function data_get_albums()
{
    global $dbh;
    $ret_arr = array();
    try
    {
        $res = $dbh->query('select aid, atitle, year from album order by year');
        foreach ($res as $data)
        {
            array_push($ret_arr, $data);
        }
    }
    catch (PDOException $e)
    {
    }
    return $ret_arr;
}

/**
 * Searches for phrases, given a set of constraints
 *
 * The constraints are specified by an associative array.  The valid
 * keys for this array are:
 *
 *  - min_words: Key should be an integer specifying the minimum number of
 *               words in the phrase
 *
 *  - max_words: Key should be an integer specifying the maximum number of
 *               words in the phrase
 *
 *  - min_songs: Key should be an integer specifying the minimum number of
 *               songs in which the phrase appears
 *
 *  - max_songs: Key should be an integer specifying the maximum number of
 *               songs in which the phrase appears
 *
 *  - min_albums: Key should be an integer specifying the minimum number of
 *                albums in which the phrase appears
 *
 *  - max_albums: Key should be an integer specifying the maximum number of
 *                albums in which the phrase appears
 *
 *  - text: Key should be a string which should be contained inside the phrase.
 *          The text has a minimum length of three, and will be ignored if it
 *          is shorter than that.
 *
 *  - albums: Key should be an array containing the numeric IDs of albums in
 *            which the phrase will be contained.
 *
 * The "sort" parameter should be one of the following:
 *
 *  - phrase_up, phrase_down: sort by phrase
 *  - songcount_up, songcount_down: sort by the "global" songcount stats
 *  - albumcount_up, albumcount_down: sort by the "global" albumcount stats
 *  - songcount_q_up, songcount_q_down: sort by the album-filtered songcount stats
 *  - albumcount_q_up, albumcount_q_down: sort by the album-filtered albumcount stats
 *
 * @param constraints An associative array definining the constraints to use
 * @param sort Which key to sort by
 * @param count Pass-by-reference variable which will contain the total number of
 *              rows available.
 * @param pagesize How many records to return
 * @param startat What record to start at for the results
 * @return An array of associative arrays.  Each item will have the keys
 *         phrase, wordcount, songcount, songcount_q, albumcount, and
 *         albumcount_q.
 *
 */
function data_do_search($constraints, $sort, &$count, $pagesize, $startat=0)
{
    global $dbh;
    $ret_arr = array();

    $where_arr = array();
    $where = '';
    $complex = false;
    $tables = 'phrase p';
    $count = 0;

    // Process our constraints
    $params = array();
    $albumcount_q_sql = '';
    $songcount_q_sql = '';
    if (is_array($constraints))
    {
        // Process "albums" first because our SQL gets more complex if we limit by
        // album.
        if (array_key_exists('albums', $constraints))
        {
            $val = $constraints['albums'];

            $complex = true;
            if (!is_array($val))
            {
                $val = array($val);
            }

            $albums_temp = array();
            $albumcount_q_arr = array();
            $songcount_q_arr = array();
            foreach ($val as $album)
            {
                array_push($albums_temp, 'albumcount_' . (int)$album . '=1');
                array_push($albumcount_q_arr, 'albumcount_' . (int)$album);
                array_push($songcount_q_arr, 'songcount_' . (int)$album);
            }
            array_push($where_arr, '(' . implode(' or ', $albums_temp) . ')');

            $songcount_q_sql = '(' . implode('+', $songcount_q_arr) . ')';
            $albumcount_q_sql = '(' . implode('+', $albumcount_q_arr) . ')';
        }
     
        // Now process everything else
        foreach ($constraints as $key => $val)
        {
            switch ($key)
            {

                case 'albums':
                    // Nothing, we already processed it outside this loop
                    break;

                case 'text':
                    if (strlen($val) > 2)
                    {
                        array_push($where_arr, 'p.phrase like :text');
                        $params['text'] = '%' . $val . '%';
                    }
                    break;

                case 'min_words':
                    array_push($where_arr, 'p.wordcount >= :min_words');
                    $params[':min_words'] = (int)$val;
                    break;

                case 'max_words':
                    array_push($where_arr, 'p.wordcount <= :max_words');
                    $params[':max_words'] = (int)$val;
                    break;

                case 'min_songs':
                    if ($complex)
                    {
                        array_push($where_arr, $songcount_q_sql . ' >= :min_songs');
                    }
                    else
                    {
                        array_push($where_arr, 'p.songcount >= :min_songs');
                    }
                    $params[':min_songs'] = (int)$val;
                    break;

                case 'max_songs':
                    if ($complex)
                    {
                        array_push($where_arr, $songcount_q_sql . ' <= :max_songs');
                    }
                    else
                    {
                        array_push($where_arr, 'p.songcount <= :max_songs');
                    }
                    $params[':max_songs'] = (int)$val;
                    break;

                case 'min_albums':
                    if ($complex)
                    {
                        array_push($where_arr, $albumcount_q_sql . ' >= :min_albums');
                    }
                    else
                    {
                        array_push($where_arr, 'p.albumcount >= :min_albums');
                    }
                    $params[':min_albums'] = (int)$val;
                    break;

                case 'max_albums':
                    if ($complex)
                    {
                        array_push($where_arr, $albumcount_q_sql . ' <= :max_albums');
                    }
                    else
                    {
                        array_push($where_arr, 'p.albumcount <= :max_albums');
                    }
                    $params[':max_albums'] = (int)$val;
                    break;
            }
        }
    }

    // Figure out what we're sorting on
    switch ($sort)
    {
        case 'phrase_up':
            $sql_orderby = ' order by phrase, songcount_q, albumcount_q ';
            break;

        case 'phrase_down':
            $sql_orderby = ' order by phrase desc, songcount_q desc, albumcount_q desc ';
            break;

        case 'albumcount_up':
            $sql_orderby = ' order by albumcount, songcount, phrase ';
            break;

        case 'albumcount_down':
            $sql_orderby = ' order by albumcount desc, songcount desc, phrase ';
            break;

        case 'albumcount_q_up':
            $sql_orderby = ' order by albumcount_q, songcount_q, albumcount, songcount, phrase ';
            break;

        case 'albumcount_q_down':
            $sql_orderby = ' order by albumcount_q desc, songcount_q desc, albumcount desc, songcount desc, phrase ';
            break;

        case 'songcount_up':
            $sql_orderby = ' order by songcount, albumcount, phrase ';
            break;

        case 'songcount_down':
            $sql_orderby = ' order by songcount desc, albumcount desc, phrase ';
            break;

        case 'songcount_q_up':
            $sql_orderby = ' order by songcount_q, albumcount_q, songcount, albumcount, phrase ';
            break;

        case 'songcount_q_down':
        default:
            $sql_orderby = ' order by songcount_q desc, albumcount_q desc, songcount desc, albumcount desc, phrase ';
            break;
    }

    // Construct our WHERE query
    if (count($where_arr) > 0)
    {
        $where = 'where ' . implode(' and ', $where_arr);
    }

    // Run the SQL
    $fields = 'distinct phrase, wordcount, songcount, albumcount';
    $fields_no_count = '';
    if ($complex)
    {
        if ($albumcount_q_sql != '')
        {
            $fields_no_count .= ', ' . $albumcount_q_sql . ' as albumcount_q';
            $fields_no_count .= ', ' . $songcount_q_sql . ' as songcount_q';
        }
        else
        {
            $fields_no_count .= ', 0 as albumcount_q, 0 as q_songcount_q';
        }
    }
    else
    {
        $fields_no_count .= ', albumcount as albumcount_q, songcount as songcount_q';
    }
    $sql_limit = 'limit ' . (int)$startat . ',' . (int)$pagesize;
    $sql_count = 'select count(' . $fields . ') record_count from ' . $tables . ' ' . $where;
    $sql_main = 'select ' . $fields . $fields_no_count . ' from ' . $tables . ' ' . $where . $sql_orderby . $sql_limit;
    //print '<pre>' . $sql_count . "</pre>\n";
    //print '<pre>' . $sql_main . "</pre>\n";
    try
    {
        // First grab the count
        $sth = $dbh->prepare($sql_count);
        $sth->execute($params);
        $data = $sth->fetch(PDO::FETCH_ASSOC);
        $count = $data['record_count'];
        $sth = null;

        // TODO: modify limit if $startat is too large, based on $count

        // Now the actual query
        $sth = $dbh->prepare($sql_main);
        $sth->execute($params);
        while ($data = $sth->fetch(PDO::FETCH_ASSOC))
        {
            array_push($ret_arr, $data);
        }
        $sth = null;
    }
    catch (PDOException $e)
    {
    }
    return $ret_arr;
}

/**
 * Given a phrase, return a list of songs which contain the phrase
 *
 * @param phrase The phrase to search for
 * @return An array of associative arrays.  Each item will have the keys
 *         sid, stitle, lyrics, aid, and atitle.
 */
function data_get_songlist($phrase)
{
    global $dbh;
    $ret_arr = array();
    $sql = 'select s.sid, stitle, lyrics, a.aid, atitle from phrase p, p2s, song s, album a where p.pid=p2s.pid and p2s.sid=s.sid and s.aid=a.aid and phrase=:phrase order by a.year, s.sid';
    try
    {
        $sth = $dbh->prepare($sql);
        $sth->execute(array(':phrase' => $phrase));
        while ($data = $sth->fetch(PDO::FETCH_ASSOC))
        {
            array_push($ret_arr, $data);
        }
        $sth = null;
    }
    catch (PDOException $e)
    {
    }
    return $ret_arr;
}

