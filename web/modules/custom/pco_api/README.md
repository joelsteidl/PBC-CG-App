# PCO API
API client for Planning Center. https://planningcenter.github.io/api-docs/

The PCO API (pco_api) module takes advantage of the Drupal httpClient https://api.drupal.org/api/drupal/core!lib!Drupal.php/function/Drupal%3A%3AhttpClient/8.2.x.

## Dependencies

Key Module - https://www.drupal.org/project/key

## Configuration

### Create a Personal Access Token in Planning Center.

This module currently uses the Personal Access Token method for authentication. You can create credentials by visiting https://accounts.planningcenteronline.com/ You should end up with a token and secret.

### Drupal configuration

1. Create a Key
   1. Visit /admin/config/system/keys/add
   2. Settings ![Key settings](https://www.evernote.com/l/AMl-0tHsHyRELK0nP8Ms6O4fSbvA2NjI9vkB/image.png)
   3. **Note:** If you go the prefferred file route for the secret, please make sure there is no white space in the file!
2. Configure API Settings
   1. Add your API token, the API base url and reference the secret created in step #1
   2. Settings ![PCO API settings](https://www.evernote.com/l/AMmcWL4KRX9Il7urBNECTnLZa4pP_p1TTnQB/image.png)

## Usage

This module provides a pco_api.client service that can be used in Drupal hooks or in Classes using Dependency Injection.

*Examples coming soon.*
