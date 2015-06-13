#!/bin/bash
LC_ALL=ja_JP.UTF-8 sort update-filepath.tsv -r | LC_ALL=C uniq > update-filepath.t
mv update-filepath.t{,sv}
