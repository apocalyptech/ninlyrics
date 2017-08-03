#!/usr/bin/env python
# vim: set expandtab tabstop=4 shiftwidth=4:

import os
import sys
import MySQLdb

lyricsdir = 'albums'

class Song(object):
    def __init__(self, title):
        self.title = title
        self.lines = []
        self.lines_full = []
        self.phrases = {}
        self.dbid = None
    def addline(self, line):
        self.lines.append(line)
    def addline_full(self, line):
        self.lines_full.append(line)
    def _phrasegen(self, line):
        words = line.split()
        phrases = []
        for i in range(len(words)):
            for j in range(i+1, len(words)+1):
                phrases.append(' '.join(words[i:j]))
        return phrases
    def process(self):
        for line in self.lines:
            for phrase in self._phrasegen(line):
                self.phrases[phrase] = 1

class Album(object):
    def __init__(self, filename):
        self.title = None
        self.year = None
        self.songs = []
        self.phrases = {}
        self.dbid = None
        with open(filename) as df:
            cursong = None
            for (idx, line) in enumerate(df.readlines()):
                line = line.strip()
                lower = line.lower()

                if line == '':
                    if cursong is not None:
                        cursong.addline_full(line)
                    continue
                elif lower[0:7] == 'album: ':
                    self.title = line[7:]
                elif lower[0:6] == 'year: ':
                    self.year = line[6:]
                elif lower[0:6] == 'song: ':
                    cursong = Song(line[6:])
                    self.songs.append(cursong)
                else:
                    if cursong is None:
                        raise Exception("%s: Unknown line %d: %s" % (filename, idx+1, line))
                    else:
                        cursong.addline(lower)
                        cursong.addline_full(line)

        self.process_songs()

    def process_songs(self):
        """
        Loops through all our tracks to process 'em.
        """
        for song in self.songs:
            song.process()
            for phrase in song.phrases:
                if phrase not in self.phrases:
                    self.phrases[phrase] = 0
                self.phrases[phrase] += 1

# Read in database values
dbhost = None
dbname = None
dbuser = None
dbpass = None
if 'XDG_CONFIG_HOME' in os.environ:
    config_home = os.environ['XDG_CONFIG_HOME']
else:
    config_home = os.path.join(os.path.expanduser('~'), '.config')
dbfile = os.path.join(config_home, 'ninlyrics', 'dbinfo.txt')
if os.path.exists(dbfile):
    with open(dbfile) as df:
        for line in df.readlines():
            (key, val) = line.strip().split(': ')
            if key == 'dbhost':
                dbhost = val
            elif key == 'dbname':
                dbname = val
            elif key == 'dbuser':
                dbuser = val
            elif key == 'dbpass':
                dbpass = val
if not dbhost or not dbname or not dbuser or not dbpass:
    print """
Database config file not found or is invalid.  Create one at the following path:

%s

You can find an example here at dbinfo-sample.txt.
""" % (dbfile)
    sys.exit(1)

# Build list of files to process
filenames = []
for root, dirs, files in os.walk(lyricsdir):
    for filename in files:
        if filename[-4:] == '.txt':
            filenames.append('%s/%s' % (root, filename))

# Read through each album and process
albums = []
for filename in filenames:
    albums.append(Album(filename))

# Now start importing into the DB
dbconn = MySQLdb.connect(host = dbhost, user = dbuser, passwd = dbpass, db = dbname)
dbcurs = dbconn.cursor(MySQLdb.cursors.DictCursor)
phrases = {}
phrases_album = {}
phrases_song = {}

# Truncate everything first
dbcurs.execute('set foreign_key_checks = 0')
dbcurs.execute('truncate p2s')
dbcurs.execute('truncate phrase')
dbcurs.execute('truncate song')
dbcurs.execute('truncate album')
dbcurs.execute('set foreign_key_checks = 1')
dbconn.commit()

# Clean up our 'phrase' table
#
# This may be as good a place as any to mention why we're doing this.  It's
# a bit silly, certainly.  Let's say that without denormalization, we want
# to find the phrases contained in at least three songs, from a pool of
# three specific albums.  Without denormalization, the query looks like this:
#
#    SELECT DISTINCT phrase
#    FROM phrase p, p2s
#    WHERE p.pid=p2s.pid AND
#          (p2s.aid=1 OR p2s.aid=2 OR p2s.aid=3)
#    GROUP BY phrase
#    HAVING COUNT(DISTINCT sid) >= 3
#
# ... no problem, really.  The query runs just fine.  With denormalization, the
# same query looks like this:
#
#    SELECT DISTINCT phrase
#    FROM phrase p
#    WHERE (songcount_1+songcount_2+songcount_3) >= 3
#
# So, simpler SQL, at the expense of abusing the relational structure.
# The other benefit other than simplicity, though, is that in my tests, that
# simpler SQL also happens to be a good six times faster than the one which
# uses GROUP BY.  Not that there's ever going to be a huge number of people
# banging on this data, but it's a pretty big performance gain, and the dataset
# is both quite small by RDBMS standards, and extremely resistant to change.
# (ie: barring my testing and tweaking, this data is going to change at most
# once every year or two.)
#
# So yeah, we're denormalizing both the song-counts and the album-counts right
# into the phrase table, using separate fields for each album.  It's uglier
# and makes me twitch a little bit, but I don't think it's worth having
# expensive SQL just to be elegant, in this case.
dbcurs.execute('desc phrase')
todelete = []
for row in dbcurs:
    if row['Field'][:10] == 'songcount_' or row['Field'][:11] == 'albumcount_':
        todelete.append(row['Field'])
if len(todelete) > 0:
    print 'Deleting old denormalized aggregate fields'
    for field in todelete:
        dbcurs.execute('alter table phrase drop %s' % (field))
    dbconn.commit()

# Loop and import our albums and songs
song_to_album = {}
for album in albums:
    print 'Importing album: %s' % (album.title)
    dbcurs.execute('insert into album (atitle, year) values (%s, %s)', (album.title, album.year))
    album.dbid = dbconn.insert_id()
    dbcurs.execute('alter table phrase add songcount_%d int not null default 0, add albumcount_%d int not null default 0' % (album.dbid, album.dbid))
    for song in album.songs:
        dbcurs.execute('insert into song (aid, stitle, lyrics) values (%s, %s, %s)', (album.dbid, song.title, "\n".join(song.lines_full)))
        song.dbid = dbconn.insert_id()
        song_to_album[song.dbid] = album.dbid
        for phrase in song.phrases.keys():
            if phrase not in phrases:
                phrases[phrase] = {}
                phrases_album[phrase] = {}
                phrases_song[phrase] = {}
            phrases[phrase][song.dbid] = True
            phrases_album[phrase][album.dbid] = True
            if album.dbid not in phrases_song[phrase]:
                phrases_song[phrase][album.dbid] = {}
            phrases_song[phrase][album.dbid][song.dbid] = True
    dbconn.commit()

# Now run through all of our phrases and attempt to weed out effectively-identical
# phrases.  For instance, "nothing can stop" is probably functionally identical to
# "nothing can stop me", and the latter is more meaningful than the former.
print 'Pruning phrases'
phrase_blacklist = {}
for phrase, songs in phrases.items():

    # Check to see if we're already in the blacklist
    if phrase in phrase_blacklist:
        continue

    # Split this up into words
    words = phrase.split()
    
    # Single-word phrases can be skipped outright
    if len(words) == 1:
        continue

    # Get a list of sub-phrases that we'll search for.
    complist = []
    for i in range(len(words)):
        for j in range(i+1, len(words)+1):
            newphrase = ' '.join(words[i:j])
            if newphrase != phrase:
                complist.append(newphrase)

    # Now loop through those phrases and see what we can do...
    for compphrase in complist:
        if compphrase not in phrase_blacklist and compphrase in phrases:
            compsongs = phrases[compphrase]

            # Check the length of our song dicts; if they're different then
            # there's no reason to do a more comprehensive check
            if len(songs) != len(compsongs):
                continue

            # Song lengths are the same, check to see if any of them differ
            for sid in songs:
                if sid not in compsongs:
                    continue
            for sid in compsongs:
                if sid not in songs:
                    continue

            # If we get here, then our two phrases are functionally identical,
            # so prune out the one that's less useful (and break out of our
            # comparison loop)
            phrase_blacklist[compphrase] = True
            #print '"%s" is better than "%s"' % (phrase, compphrase)

print '%d phrases, %d in the blacklist (%d remain)' % (len(phrases), len(phrase_blacklist), len(phrases)-len(phrase_blacklist))

# Now import our phrases (and mappings) into the DB
print 'Importing phrases...'
sql_fieldname_list = ['phrase', 'wordcount', 'songcount', 'albumcount']
sql_var_list = ['%s', '%s', '%s', '%s']
for album in albums:
    sql_fieldname_list.append('albumcount_%d' % (album.dbid))
    sql_fieldname_list.append('songcount_%d' % (album.dbid))
    sql_var_list.extend(['%s', '%s'])
sql = 'insert into phrase(%s) values (%s)' % (
        ', '.join(sql_fieldname_list),
        ', '.join(sql_var_list),
        )
for phrase in phrases:
    if phrase not in phrase_blacklist:
        phrase_data_list = [phrase, len(phrase.split()), len(phrases[phrase]), len(phrases_album[phrase])]
        for album in albums:
            if album.dbid in phrases_album[phrase]:
                phrase_data_list.append(1)
            else:
                phrase_data_list.append(0)
            if (album.dbid in phrases_song[phrase]):
                phrase_data_list.append(len(phrases_song[phrase][album.dbid]))
            else:
                phrase_data_list.append(0)
        dbcurs.execute(sql, phrase_data_list)
        phrase_id = dbconn.insert_id()
        for song_id in phrases[phrase]:
            dbcurs.execute('insert into p2s (pid, sid, aid) values (%s, %s, %s)', (phrase_id, song_id, song_to_album[song_id]))
dbconn.commit()

# Clean up
print 'Done!'
dbcurs.close()
dbconn.close()
