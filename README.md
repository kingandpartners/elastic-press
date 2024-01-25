# ElasticPress

WordPress plugin to serialize all data inclusive of ACF data into ElasticSearch.

## Development

### Quick Elasticsearch Reminders
```
# get status
curl -X GET 'http://localhost:9200/'

# get data
curl -X GET 'http://localhost:9200/_all'

# clear data
curl -X DELETE 'http://localhost:9200/_all'
```

## Testing

### Setup

**Elasticsearch needs to be running on your local**
```
# assuming you have standard
# homebrew install
elasticsearch -d
```

**install PHPUnit Globally**
```
composer global require "phpunit/phpunit:7.*"
```
and add Global Composer Bin to $PATH (.bashrc or whatever shell config)
```
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

**setup local env file**
```
cp .env.example .env.test
```

### install the tests
```
bin/install-tests
```

### run
```
bin/run-tests
```




