#
# Simple makefile for creating the zip for the discord-link plugin
#

.PHONY: discord-link
discord-link:
	rm -f discord-link.zip
	(cd .. && zip discord-link/discord-link \
	    $$(find discord-link/LICENSE discord-link/*.php \
	       discord-link/admin discord-link/public discord-link/includes \
	       -name '.*' -prune -o -print))
