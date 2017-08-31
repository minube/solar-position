<?php

namespace SolarPosition;

use DateTime;
use DateTimeZone;

class Calculator
{
    const CIVIL_DEPRESSION = 6.0;
    const NAUTICAL_DEPRESSION = 12.0;
    const ASTRONOMICAL_DEPRESSION = 18.0;
    const MINUTES_BY_DAY = 1440.0;
    const MAX_JULIAN_DAY = 2299160.4999999;
    const OFFSET_JULIAN_CENTURY = 2451545.0;
    const MULTIPLIER_JULIAN_CENTURY = 36525.0;
    const MAX_LATITUDE = 89.8;
    const MIN_LATITUDE = -89.8;
    const SUNSET_DEPRESSION = -0.833;
    const SUNRISE_DEPRESSION = 0.833;
    const TIME_UNIT = 60.0;
    const DAY_TIME_UNIT = 24;
    
    /**
     * Calculate timezone offset days from timezone text identifier to UTC as reference (i.e. "Europe/Amsterdam")
     * @param string $timeZoneText
     * @return float|int
     */
    protected function calculateTimeZoneOffset($timeZoneText = "")
    {
        $offset = 0;
        if ($timeZoneText != "") {
            $zone = new DateTimeZone($timeZoneText);
            $offset = ($zone->getOffset(new DateTime('UTC')) / self::MINUTES_BY_DAY);
            echo($zone->getOffset(new DateTime('UTC')));
            echo $offset;
        }
        return $offset;
    }
    
    /**
     * Calculate julian day from date (day, month, year) and timezone
     * @param array $date
     * @param null $timeZoneText
     * @return float|int
     */
    public function calculateJulianDay($date, $timeZoneText = null)
    {
        $day = $date['day'];
        $month = $date['month'];
        $year = $date['year'];
        $day += $this->calculateTimeZoneOffset($timeZoneText);
        
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        
        $julianDay = floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day - 1524.5;
        
        if ($julianDay > self::MAX_JULIAN_DAY) {
            $tmpYear = floor($year / 100.0);
            $julianDay += 2 - $tmpYear + floor($tmpYear / 4.0);
        }
        return $julianDay;
    }
    
    /**
     * Calculate Julian Day from Julian Century value
     * @param $julianCentury
     * @return mixed
     */
    protected function julianCenturyToJulianDay($julianCentury)
    {
        return ($julianCentury * self::MULTIPLIER_JULIAN_CENTURY) + self::OFFSET_JULIAN_CENTURY;
    }
    
    /**
     * Calculate Julian Century from Julian Day value
     * @param $julianDay
     * @return float
     */
    protected function julianDayToJulianCentury($julianDay)
    {
        return ($julianDay - self::OFFSET_JULIAN_CENTURY) / self::MULTIPLIER_JULIAN_CENTURY;
    }
    
    /**
     * Calculate mean obliquity of ecliptic (term used by astronomers for the inclination of Earth's equator
     * with respect to the ecliptic) from Julian Century
     * @param $julianCentury
     * @return float obliquity in degrees
     */
    protected function calcMeanObliquityOfEcliptic($julianCentury)
    {
        $seconds = 21.448 - $julianCentury * (46.8150 + ($julianCentury * (0.00059 - $julianCentury * (0.001813))));
        return 23.0 + ((26.0 + ($seconds / self::TIME_UNIT)) / self::TIME_UNIT);
        
    }
    
    /**
     * Corrected obliquity in degrees
     * @param $julianCentury
     * @return float
     */
    protected function calcObliquityCorrection($julianCentury)
    {
        $omega = 125.04 - (1934.136 * $julianCentury);
        $obliquityInDegrees = $this->calcMeanObliquityOfEcliptic($julianCentury) + (0.00256 * cos(deg2Rad($omega)));
        return $obliquityInDegrees;
    }
    
    /**
     * Calculate mean anomaly of the sun position
     * @param $julianCentury
     * @return float
     */
    protected function calcGeomMeanAnomaly($julianCentury)
    {
        return 357.52911 + $julianCentury * (35999.05029 - 0.0001537 * $julianCentury);
    }
    
    /**
     * Geometric mean longitude of sun position
     * @param $julianCentury
     * @return float|int
     */
    protected function calcGeomMeanLongitude($julianCentury)
    {
        $geomLong = 280.46646 + $julianCentury * (36000.76983 + 0.0003032 * $julianCentury);
        $geomLong = $geomLong % 360;
        return $geomLong;
    }
    
    /**
     * Eccentricity Earth Orbit calculation
     * @param $julianCentury
     * @return float
     */
    protected function calcEccentricityEarthOrbit($julianCentury)
    {
        return 0.016708634 - $julianCentury * (0.000042037 + 0.0000001267 * $julianCentury);
    }
    
    /**
     * Equation of Time calculation in minutes of time
     * @param $julianCentury
     * @return float
     */
    protected function calcEquationOfTime($julianCentury)
    {
        $meanLong = $this->calcGeomMeanLongitude($julianCentury);
        $meanLongInRad = deg2Rad($meanLong);
        $eccentricity = $this->calcEccentricityEarthOrbit($julianCentury);
        $meanAnomaly = $this->calcGeomMeanAnomaly($julianCentury);
        $obliquity = tan(deg2Rad($this->calcObliquityCorrection($julianCentury)) / 2.0);
        $obliquity *= $obliquity;
        
        $sin2MeanLong = sin(2.0 * $meanLongInRad);
        $sinMeanAnomaly = sin(deg2Rad($meanAnomaly));
        $cos2MeanLong = cos(2.0 * $meanLongInRad);
        $sin4MeanLong = sin(4.0 * $meanLongInRad);
        $sin2MeanAnomaly = sin(2.0 * deg2Rad($meanAnomaly));
        
        $equationOfTime = $obliquity * $sin2MeanLong - 2.0 * $eccentricity * $sinMeanAnomaly +
            4.0 * $eccentricity * $obliquity * $sinMeanAnomaly * $cos2MeanLong -
            0.5 * $obliquity * $obliquity * $sin4MeanLong - 1.25 * $eccentricity * $eccentricity * $sin2MeanAnomaly;
        return rad2Deg($equationOfTime) * 4.0;
    }
    
    /**
     * Equation of the center in degrees
     * @param $julianCentury
     * @return float
     */
    protected function calcEquationOfCenter($julianCentury)
    {
        $meanAnomaly = $this->calcGeomMeanAnomaly($julianCentury);
        $meanAnomalyInRad = deg2Rad($meanAnomaly);
        $sinMeanAnomaly = sin($meanAnomalyInRad);
        $sin2MeanAnomaly = sin($meanAnomalyInRad + $meanAnomalyInRad);
        $sin3MeanAnomaly = sin($meanAnomalyInRad + $meanAnomalyInRad + $meanAnomalyInRad);
        $equationOfCenter = $sinMeanAnomaly * (1.914602 - $julianCentury * (0.004817 + 0.000014 * $julianCentury)) +
            $sin2MeanAnomaly * (0.019993 - 0.000101 * $julianCentury) + $sin3MeanAnomaly * 0.000289;
        return $equationOfCenter;
    }
    
    /**
     * Calculate true (ecliptic) longitude of the sun in degrees
     * @param $julianCentury
     * @return float|int
     */
    protected function calcTrueLongitude($julianCentury)
    {
        $meanLongitude = $this->calcGeomMeanLongitude($julianCentury);
        $equationOfCenter = $this->calcEquationOfCenter($julianCentury);
        return $meanLongitude + $equationOfCenter;
    }
    
    /**
     * Calculate apparent logintude of the sun in degrees
     * @param $julianCentury
     * @return float|int
     */
    protected function calcApparentLongitude($julianCentury)
    {
        $trueLongitude = $this->calcTrueLongitude($julianCentury);
        $omega = 125.04 - 1934.136 * $julianCentury;
        return $trueLongitude - 0.00569 - 0.00478 * sin(deg2Rad($omega));
    }
    
    /**
     * Sun declination angle in degrees
     * @param $julianCentury
     * @return float
     */
    protected function calcDeclination($julianCentury)
    {
        $obliquity = $this->calcObliquityCorrection($julianCentury);
        $lambda = $this->calcApparentLongitude($julianCentury);
        $sinT = sin(deg2Rad($obliquity)) * sin(deg2Rad($lambda));
        $theta = rad2Deg(asin($sinT));
        return $theta;
    }
    
    /**
     * Hour angle calculation
     * @param $latitude
     * @param $solarDec
     * @param $solarDepression
     * @return float
     */
    protected function calcHourAngle($latitude, $solarDec, $solarDepression)
    {
        $latitudeInRad = deg2rad($latitude);
        $solarDecInRad = deg2rad($solarDec);
        $negativeFlag = 1;
        if ($solarDepression < 0) {
            $solarDepression = abs($solarDepression);
            $negativeFlag = -1;
        }
        $hourAngle = (acos((cos(deg2rad(90.0 + $solarDepression)) /
                (cos($latitudeInRad) * cos($solarDecInRad))) - (tan($latitudeInRad) * tan($solarDecInRad))));
        return $hourAngle * $negativeFlag;
    }
    
    /**
     * Create UTC DateTime object
     * @param $timeUTC
     * @param $date
     * @return DateTime
     */
    protected function getUtcDateTime($timeUTC, $date)
    {
        $dateTimeUtc = new DateTime("UTC");
        $timeUTC /= self::TIME_UNIT;
        $hour = (int)$timeUTC;
        $minute = (int)(($timeUTC - $hour) * self::TIME_UNIT);
        $second = (int)(((($timeUTC - $hour) * self::TIME_UNIT) - $minute) * self::TIME_UNIT);
        
        $minute += (int)floor($second / self::TIME_UNIT);
        $second -= (int)(self::TIME_UNIT * floor($second / self::TIME_UNIT));
        $hour += (int)floor($minute / self::TIME_UNIT);
        $minute -= (int)(self::TIME_UNIT * floor($minute / self::TIME_UNIT));
        $date['day'] += (int)floor($hour / self::DAY_TIME_UNIT);
        $hour -= (int)(self::DAY_TIME_UNIT * floor($hour / self::DAY_TIME_UNIT));
        
        $dateTimeUtc->setDate($date['year'], $date['month'], $date['day']);
        $dateTimeUtc->setTime($hour, $minute, $second);
        return $dateTimeUtc;
    }
    
    /**
     * UTC time calculation
     * @param $julianCentury
     * @param $latitude
     * @param $longitude
     * @param $depression
     * @return float
     */
    protected function calculateUtcTime($julianCentury, $latitude, $longitude, $depression)
    {
        $equationOfTime = $this->calcEquationOfTime($julianCentury);
        $sunDeclination = $this->calcDeclination($julianCentury);
        $hourAngle = rad2deg($this->calcHourAngle($latitude, $sunDeclination, $depression));
        $delta = -$longitude - $hourAngle;
        $timeDiff = 4.0 * $delta;
        $utcTime = 720.0 + $timeDiff - $equationOfTime;
        return $utcTime;
    }
    
    /**
     * Calculate UTC Time of Solar Noon
     * @param $julianDay
     * @param $longitude
     * @return float
     */
    protected function calculateUtcNoonTime($julianDay, $longitude)
    {
        $julianCentury = $this->julianDayToJulianCentury($julianDay + 0.5 - $longitude / 360.0);
        $equationOfTime = $this->calcEquationOfTime($julianCentury);
        $utcTime = 720.0 + (-$longitude * 4.0) - $equationOfTime;
        return $utcTime;
    }
    
    /**
     * Calculate solar time depending on a particular location, date and solar depression
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param $depression
     * @param string $timeZone
     * @return DateTime
     */
    protected function calculateSolarTime($date, $latitude, $longitude, $depression, $timeZone = "")
    {
        $julianCentury = $this->julianDayToJulianCentury($this->calculateJulianDay($date, $timeZone));
        if ($latitude > self::MAX_LATITUDE) {
            $latitude = self::MAX_LATITUDE;
        }
        if ($latitude < self::MIN_LATITUDE) {
            $latitude = self::MIN_LATITUDE;
        }
        $utcTime = $this->calculateUtcTime($julianCentury, $latitude, $longitude, self::SUNRISE_DEPRESSION);
        $newJulianCentury = $this->julianDayToJulianCentury(
            $this->julianCenturyToJulianDay($julianCentury) + $utcTime / 1440.0
        );
        $utcTime = $this->calculateUtcTime($newJulianCentury, $latitude, $longitude, $depression);
        return $this->getUtcDateTime($utcTime, $date);
    }
    
    /**
     * Get Solar Noon Time, when the Sun is at the highest altitude
     * @param $date
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getSolarNoon($date, $longitude, $timeZone = "")
    {
        $utcTime = $this->calculateUtcNoonTime($this->calculateJulianDay($date, $timeZone), $longitude);
        return $this->getUtcDateTime($utcTime, $date);
    }
    
    /**
     * Get Sunset hour of a particular location and date
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getSunset($date, $latitude, $longitude, $timeZone = "")
    {
        return $this->calculateSolarTime($date, $latitude, $longitude, self::SUNSET_DEPRESSION, $timeZone);
    }
    
    /**
     * Get Sunrise hour of a particular location and date
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getSunrise($date, $latitude, $longitude, $timeZone = "")
    {
        return $this->calculateSolarTime($date, $latitude, $longitude, self::SUNRISE_DEPRESSION, $timeZone);
    }
    
    /**
     * Get the beginning of the Civil Twilight phase.
     * The Sun is just below the horizon, so there is generally enough natural light to carry out most outdoor
     * activities.
     * Source: https://www.timeanddate.com/astronomy/
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getCivilTwilightBegin($date, $latitude, $longitude, $timeZone = "")
    {
        return $this->calculateSolarTime($date, $latitude, $longitude, self::CIVIL_DEPRESSION, $timeZone);
    }
    
    /**
     * Get the end of the Civil Twilight phase.
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getCivilTwilightEnd($date, $latitude, $longitude, $timeZone = "")
    {
        return $this->calculateSolarTime($date, $latitude, $longitude, -self::CIVIL_DEPRESSION, $timeZone);
    }
    
    /**
     * Get the beginning of the Nautical Twilight phase.
     * Nautical twilight is the second twilight phase, when both the horizon and the brighter stars are usually visible
     * at this time, making it possible to navigate at sea.
     * Source: https://www.timeanddate.com/astronomy/
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getNauticalTwilightBegin($date, $latitude, $longitude, $timeZone = "")
    {
        return $this->calculateSolarTime($date, $latitude, $longitude, self::NAUTICAL_DEPRESSION, $timeZone);
    }
    
    /**
     * Get the end of the Nautical Twilight phase.
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getNauticalTwilightEnd($date, $latitude, $longitude, $timeZone = "")
    {
        return $this->calculateSolarTime($date, $latitude, $longitude, -self::NAUTICAL_DEPRESSION, $timeZone);
    }
    
    /**
     * Get the beginning of the Astronomical Twilight phase.
     * Darkest phase. It is the earliest stage of dawn in the morning and the last stage of dusk in the evening.
     * Source: https://www.timeanddate.com/astronomy/
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getAstronomicalTwilightBegin($date, $latitude, $longitude, $timeZone = "")
    {
        return $this->calculateSolarTime(
            $date,
            $latitude,
            $longitude,
            self::ASTRONOMICAL_DEPRESSION,
            $timeZone);
    }
    
    /**
     * Get the end of the Astronomical Twilight phase.
     * @param $date
     * @param $latitude
     * @param $longitude
     * @param string $timeZone
     * @return DateTime
     */
    public function getAstronomicalTwilightEnd($date, $latitude, $longitude, $timeZone = "")
    {
        return $this->calculateSolarTime($date, $latitude, $longitude, -self::ASTRONOMICAL_DEPRESSION, $timeZone);
    }
}
