{{- define "symfony2026.capacityConfigMapName" -}}
{{- $capacity := required (printf "generated.postgresCapacity.%s is required" .service) (index .root.Values.generated.postgresCapacity .service) -}}
{{- if not (regexMatch "^[1-9][0-9]*$" (toString $capacity)) -}}
{{- fail (printf "generated.postgresCapacity.%s must be a positive integer" .service) -}}
{{- end -}}
{{- printf "postgres-capacity-%s-%s" .service (toString $capacity) -}}
{{- end -}}
