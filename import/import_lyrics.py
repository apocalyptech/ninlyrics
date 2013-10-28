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
    config_home = os.path.expanduser('~')
dbfile = os.path.join(config_home, '.config', 'ninlyrics', 'dbinfo.txt')
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

# Truncate everything first
dbcurs.execute('truncate p2s')
dbcurs.execute('truncate phrase')
dbcurs.execute('truncate song')
dbcurs.execute('truncate album')
dbconn.commit()

# Loop and import our albums and songs
song_to_album = {}
for album in albums:
    print 'Importing album: %s' % (album.title)
    dbcurs.execute('insert into album (atitle, year) values (%s, %s)', (album.title, album.year))
    album.dbid = dbconn.insert_id()
    for song in album.songs:
        dbcurs.execute('insert into song (aid, stitle, lyrics) values (%s, %s, %s)', (album.dbid, song.title, "\n".join(song.lines_full)))
        song.dbid = dbconn.insert_id()
        song_to_album[song.dbid] = album.dbid
        for phrase in song.phrases.keys():
            if phrase not in phrases:
                phrases[phrase] = {}
                phrases_album[phrase] = {}
            phrases[phrase][song.dbid] = True
            phrases_album[phrase][album.dbid] = True
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
for phrase in phrases:
    if phrase not in phrase_blacklist:
        dbcurs.execute('insert into phrase(phrase, wordcount, songcount, albumcount) values (%s, %s, %s, %s)',
                (phrase, len(phrase.split()), len(phrases[phrase]), len(phrases_album[phrase]))
            )
        phrase_id = dbconn.insert_id()
        for song_id in phrases[phrase]:
            dbcurs.execute('insert into p2s (pid, sid, aid) values (%s, %s, %s)', (phrase_id, song_id, song_to_album[song_id]))
dbconn.commit()

# Clean up
print 'Done!'
dbcurs.close()
dbconn.close()
