{{- define "symfony2026.capacityConfigMapName" -}}
{{- $capacities := .root.Values.generated.postgresCapacity | default dict -}}
{{- $capacity := required (printf "generated.postgresCapacity.%s is required; run npm run k8s:deploy" .service) (index $capacities .service) -}}
{{- printf "postgres-capacity-%s-%s" .service ($capacity | toString) -}}
{{- end -}}
