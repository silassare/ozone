#!/bin/bash

find . | grep otpl_done | while read line; do rm -r "$line"; done;