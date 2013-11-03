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
 * @param constraints An associative array definining the constraints to use
 * @param count Pass-by-reference variable which will contain the total number of
 *              rows available.
 * @param startat What record to start at for the results
 * @return An array of associative arrays.  Each item will have the keys
 *         phrase, wordcount, songcount, and albumcount.
 *
 */
function data_do_search($constraints, &$count, $startat=0)
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
    if (is_array($constraints))
    {
        foreach ($constraints as $key => $val)
        {
            switch ($key)
            {
                case 'min_words':
                    array_push($where_arr, 'p.wordcount >= :min_words');
                    $params[':min_words'] = (int)$val;
                    break;

                case 'max_words':
                    array_push($where_arr, 'p.wordcount <= :max_words');
                    $params[':max_words'] = (int)$val;
                    break;

                case 'min_songs':
                    array_push($where_arr, 'p.songcount >= :min_songs');
                    $params[':min_songs'] = (int)$val;
                    break;

                case 'max_songs':
                    array_push($where_arr, 'p.songcount <= :max_songs');
                    $params[':max_songs'] = (int)$val;
                    break;

                case 'min_albums':
                    array_push($where_arr, 'p.albumcount >= :min_albums');
                    $params[':min_albums'] = (int)$val;
                    break;

                case 'max_albums':
                    array_push($where_arr, 'p.albumcount <= :max_albums');
                    $params[':max_albums'] = (int)$val;
                    break;

                case 'text':
                    if (strlen($val) > 2)
                    {
                        array_push($where_arr, 'p.phrase like :text');
                        $params['text'] = '%' . $val . '%';
                    }
                    break;

                case 'albums':
                    $complex = true;
                    if (!is_array($val))
                    {
                        $val = array($val);
                    }

                    $albums = array();
                    foreach ($val as $album)
                    {
                        array_push($albums, 'p2s.aid = :album_' . (int)$album);
                        $params['album_' . (int)$album] = (int)$album;
                    }
                    array_push($where_arr, '(' . implode(' or ', $albums) . ')');
                    break;
            }
        }
    }

    // Some extra SQL we need for the "complex" query
    if ($complex)
    {
        $tables .= ', p2s';
        array_push($where_arr, 'p.pid = p2s.pid');
    }

    // Construct our WHERE query
    if (count($where_arr) > 0)
    {
        $where = 'where ' . implode(' and ', $where_arr);
    }

    // Run the SQL
    $fields = 'distinct phrase, wordcount, songcount, albumcount';
    $orderby = ' order by songcount desc, albumcount desc, phrase limit ' . (int)$startat . ',100';
    $sql_count = 'select count(' . $fields . ') record_count from ' . $tables . ' ' . $where;
    $sql_main = 'select ' . $fields . ' from ' . $tables . ' ' . $where . $orderby;
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

