##
# mb_olc_flights
# pre-commit cleanup script
# (C) 2012 Martin Becker <vbmazter@web.de>
##

#!/bin/sh

# not on dev host!
if [ `hostname` == "HyperZal" ]; then
	echo "Hier wird nicht aufgeräumt!"
	exit 0
fi

# remove all .svn dirs
rm -r `find . -type d -name .svn`

# omit particular files
rm -r nbproject
rm doc/*.png

# kill myself
rm `basename $0`
