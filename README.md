#block_training_architecture_moodle
# Training Architecture #

This plugin is dependent on the local_training_architecture plugin because it displays the architecture defined with it.
The goal is to improve.

Here is a presentation of the couple of plugins :
https://amupod.univ-amu.fr/video/31933-mm24_optimiser-lexperience-utilisateur-grace-aux-plugins-training_architecture-par-jeremie-pilette/

    local_training_architecture
    block_training_architecture

This plugin can be added directly in the dashboard or in the course to have the navigation.e.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/block/training_architecture

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2024 Esteban BIRET-TOSCANO <esteban.biret@gmail.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
