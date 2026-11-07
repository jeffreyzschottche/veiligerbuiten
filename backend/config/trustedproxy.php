<?php

/*
 * Coolify (Traefik) staat vóór de container en zet het echte IP van de bezoeker in
 * X-Forwarded-For. Zonder deze instelling ziet Laravel bij elk verzoek het IP van
 * Traefik, en delen alle gebruikers samen één rate-limit-teller.
 *
 * Alleen proxies op het eigen, privé Docker-netwerk vertrouwen (nooit '*'): een
 * bezoeker van buiten heeft een publiek IP en kan X-Forwarded-For dus niet vervalsen.
 * Symfony leest de keten van rechts en neemt het eerste IP dat geen proxy is.
 */
return [
    'proxies' => env('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1,::1'),
];
