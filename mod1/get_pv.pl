# check if module Encode is present
my $enc = (eval ('require "Encode.pm"')) ? '1' : '0';
# check if module URI::Escape is present
my $esc = (eval ('require "URI/Escape.pm"')) ? '1' : '0';
# check if module Geo::IP is present
my $geo = (eval ('require "Geo/IP.pm"')) ? '1' : '0';
# check if module Geo::IP::PurePerl is present
my $geopp = (eval ('require "Geo/IP/PurePerl.pm"')) ? '1' : '0';
# returns the perl version
my $ver = $];
print '<pv>'.$ver.'</pv><enc>'.$enc.'</enc><esc>'.$esc.'</esc><geo>'.$geo.'</geo><geopp>'.$geopp.'</geopp>';
exit 0;
