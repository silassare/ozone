#!/bin/sh

./csfix

find . | grep blate_cache | while read line; do rm -r "$line"; done;
