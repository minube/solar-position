# Solar Position
Calculate the sunset and sunrise times for a particular location and date, as well as the different twilight phases (civil, nautical, astronomical) times. 
More details about the different sun position phases in [timeanddate.com](https://www.timeanddate.com/astronomy/about-sun-calculator.html).

## Input
* Date: array('day', 'month', 'year')
* Latitude
* Longitude

## Output
* UTC [ DateTime](http://php.net/manual/es/class.datetime.php) object

## Example
```php
    use SolarPosition\Calculator;

    $latitude = 40.433202;
    $longitude = -3.690161;
    $date = array('day'=>23, 'month'=>6, 'year'=>2017);

    $sun = new Calculator();
    $sunsetTime = $sun->getSunset($date, $latitude,$longitude);
    /*
        DateTime Object
        (
            [date] => 2017-06-23 19:48:35
            [timezone_type] => 3
            [timezone] => UTC
        )
    */
 ```
