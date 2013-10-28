NIN Lyrics Analyzer
===================

This is a little app to analyze lyrics to Nine Inch Nails tracks and
discover phrases/words that are commonly used between songs.

The web front end is in PHP.  The import utility (which reads the lyrics
from a collection of text files) is in Python.

It's written with a MySQL backend, but it would be pretty trivial to
use a different DB backend.  The PHP component already uses PDO, and the
import script just, well, runs some INSERTs, as you'd expect.
