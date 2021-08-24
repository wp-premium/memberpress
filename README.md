# MemberPress Deployer

## Mothership Caseproof

```
curl https://mothership.caseproof.com/versions/latest/developer | jq

http https://mothership.caseproof.com/versions/latest/developer

http POST https://mothership.caseproof.com/versions/info/$MEMBERPRESS_LICENSE_KEY domain=$MEMBERPRESS_LICENSE_DOMAIN

http POST https://mothership.caseproof.com/versions/info/$MEMBERPRESS_LICENSE_KEY domain=$MEMBERPRESS_LICENSE_DOMAIN | jq '.url'
```

## Changelog

- https://memberpress.com/change-log/
- https://memberpress.com/wp-json/wp/v2/pages?slug=change-log

## Schedule

> Scheduled workflows run on the latest commit on the default or base branch.

https://docs.github.com/en/actions/reference/events-that-trigger-workflows#scheduled-events
