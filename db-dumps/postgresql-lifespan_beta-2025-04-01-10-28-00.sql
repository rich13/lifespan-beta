--
-- PostgreSQL database dump
--

-- Dumped from database version 15.4
-- Dumped by pg_dump version 15.12 (Debian 15.12-0+deb12u2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_personal_span_id_foreign;
ALTER TABLE IF EXISTS ONLY public.user_spans DROP CONSTRAINT IF EXISTS user_spans_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.user_spans DROP CONSTRAINT IF EXISTS user_spans_span_id_foreign;
ALTER TABLE IF EXISTS ONLY public.telescope_entries_tags DROP CONSTRAINT IF EXISTS telescope_entries_tags_entry_uuid_foreign;
ALTER TABLE IF EXISTS ONLY public.spans DROP CONSTRAINT IF EXISTS spans_updater_id_foreign;
ALTER TABLE IF EXISTS ONLY public.spans DROP CONSTRAINT IF EXISTS spans_type_id_foreign;
ALTER TABLE IF EXISTS ONLY public.spans DROP CONSTRAINT IF EXISTS spans_root_id_foreign;
ALTER TABLE IF EXISTS ONLY public.spans DROP CONSTRAINT IF EXISTS spans_parent_id_foreign;
ALTER TABLE IF EXISTS ONLY public.spans DROP CONSTRAINT IF EXISTS spans_owner_id_foreign;
ALTER TABLE IF EXISTS ONLY public.span_permissions DROP CONSTRAINT IF EXISTS span_permissions_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.span_permissions DROP CONSTRAINT IF EXISTS span_permissions_span_id_foreign;
ALTER TABLE IF EXISTS ONLY public.connections DROP CONSTRAINT IF EXISTS connections_type_id_foreign;
ALTER TABLE IF EXISTS ONLY public.connections DROP CONSTRAINT IF EXISTS connections_parent_id_foreign;
ALTER TABLE IF EXISTS ONLY public.connections DROP CONSTRAINT IF EXISTS connections_connection_span_id_foreign;
ALTER TABLE IF EXISTS ONLY public.connections DROP CONSTRAINT IF EXISTS connections_child_id_foreign;
DROP TRIGGER IF EXISTS update_family_connection_dates ON public.spans;
DROP TRIGGER IF EXISTS enforce_temporal_constraint ON public.connections;
DROP TRIGGER IF EXISTS connection_sync_trigger ON public.connections;
DROP INDEX IF EXISTS public.user_spans_user_id_index;
DROP INDEX IF EXISTS public.user_spans_span_id_index;
DROP INDEX IF EXISTS public.user_spans_access_level_index;
DROP INDEX IF EXISTS public.telescope_entries_type_should_display_on_index_index;
DROP INDEX IF EXISTS public.telescope_entries_tags_tag_index;
DROP INDEX IF EXISTS public.telescope_entries_family_hash_index;
DROP INDEX IF EXISTS public.telescope_entries_created_at_index;
DROP INDEX IF EXISTS public.telescope_entries_batch_id_index;
DROP INDEX IF EXISTS public.spans_updater_id_index;
DROP INDEX IF EXISTS public.spans_type_id_index;
DROP INDEX IF EXISTS public.spans_start_year_index;
DROP INDEX IF EXISTS public.spans_start_date_index;
DROP INDEX IF EXISTS public.spans_root_id_index;
DROP INDEX IF EXISTS public.spans_precision_index;
DROP INDEX IF EXISTS public.spans_permission_mode_index;
DROP INDEX IF EXISTS public.spans_parent_id_index;
DROP INDEX IF EXISTS public.spans_owner_id_index;
DROP INDEX IF EXISTS public.spans_end_date_index;
DROP INDEX IF EXISTS public.span_permissions_user_id_index;
DROP INDEX IF EXISTS public.span_permissions_span_id_index;
DROP INDEX IF EXISTS public.span_connections_span_id_idx;
DROP INDEX IF EXISTS public.personal_access_tokens_tokenable_type_tokenable_id_index;
DROP INDEX IF EXISTS public.connections_type_id_index;
DROP INDEX IF EXISTS public.connections_parent_id_index;
DROP INDEX IF EXISTS public.connections_connection_span_id_index;
DROP INDEX IF EXISTS public.connections_child_id_index;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_personal_span_id_unique;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_email_unique;
ALTER TABLE IF EXISTS ONLY public.user_spans DROP CONSTRAINT IF EXISTS user_spans_user_id_span_id_unique;
ALTER TABLE IF EXISTS ONLY public.user_spans DROP CONSTRAINT IF EXISTS user_spans_pkey;
ALTER TABLE IF EXISTS ONLY public.telescope_monitoring DROP CONSTRAINT IF EXISTS telescope_monitoring_pkey;
ALTER TABLE IF EXISTS ONLY public.telescope_entries DROP CONSTRAINT IF EXISTS telescope_entries_uuid_unique;
ALTER TABLE IF EXISTS ONLY public.telescope_entries_tags DROP CONSTRAINT IF EXISTS telescope_entries_tags_pkey;
ALTER TABLE IF EXISTS ONLY public.telescope_entries DROP CONSTRAINT IF EXISTS telescope_entries_pkey;
ALTER TABLE IF EXISTS ONLY public.spans DROP CONSTRAINT IF EXISTS spans_slug_unique;
ALTER TABLE IF EXISTS ONLY public.spans DROP CONSTRAINT IF EXISTS spans_pkey;
ALTER TABLE IF EXISTS ONLY public.span_types DROP CONSTRAINT IF EXISTS span_types_pkey;
ALTER TABLE IF EXISTS ONLY public.span_permissions DROP CONSTRAINT IF EXISTS span_permissions_span_id_user_id_group_id_permission_type_uniqu;
ALTER TABLE IF EXISTS ONLY public.span_permissions DROP CONSTRAINT IF EXISTS span_permissions_pkey;
ALTER TABLE IF EXISTS ONLY public.personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_token_unique;
ALTER TABLE IF EXISTS ONLY public.personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.migrations DROP CONSTRAINT IF EXISTS migrations_pkey;
ALTER TABLE IF EXISTS ONLY public.invitation_codes DROP CONSTRAINT IF EXISTS invitation_codes_pkey;
ALTER TABLE IF EXISTS ONLY public.invitation_codes DROP CONSTRAINT IF EXISTS invitation_codes_code_unique;
ALTER TABLE IF EXISTS ONLY public.connections DROP CONSTRAINT IF EXISTS connections_pkey;
ALTER TABLE IF EXISTS ONLY public.connections DROP CONSTRAINT IF EXISTS connections_connection_span_id_unique;
ALTER TABLE IF EXISTS ONLY public.connection_types DROP CONSTRAINT IF EXISTS connection_types_pkey;
ALTER TABLE IF EXISTS public.telescope_entries ALTER COLUMN sequence DROP DEFAULT;
ALTER TABLE IF EXISTS public.personal_access_tokens ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.migrations ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.invitation_codes ALTER COLUMN id DROP DEFAULT;
DROP TABLE IF EXISTS public.users;
DROP TABLE IF EXISTS public.user_spans;
DROP TABLE IF EXISTS public.telescope_monitoring;
DROP TABLE IF EXISTS public.telescope_entries_tags;
DROP SEQUENCE IF EXISTS public.telescope_entries_sequence_seq;
DROP TABLE IF EXISTS public.telescope_entries;
DROP TABLE IF EXISTS public.span_types;
DROP TABLE IF EXISTS public.span_permissions;
DROP MATERIALIZED VIEW IF EXISTS public.span_connections;
DROP TABLE IF EXISTS public.spans;
DROP SEQUENCE IF EXISTS public.personal_access_tokens_id_seq;
DROP TABLE IF EXISTS public.personal_access_tokens;
DROP SEQUENCE IF EXISTS public.migrations_id_seq;
DROP TABLE IF EXISTS public.migrations;
DROP SEQUENCE IF EXISTS public.invitation_codes_id_seq;
DROP TABLE IF EXISTS public.invitation_codes;
DROP VIEW IF EXISTS public.connections_spo;
DROP TABLE IF EXISTS public.connections;
DROP TABLE IF EXISTS public.connection_types;
DROP FUNCTION IF EXISTS public.validate_place();
DROP FUNCTION IF EXISTS public.update_place_hierarchy();
DROP FUNCTION IF EXISTS public.update_family_connection_dates();
DROP FUNCTION IF EXISTS public.update_connection_dates();
DROP FUNCTION IF EXISTS public.sync_span_connections();
DROP FUNCTION IF EXISTS public.check_temporal_constraint();
DROP SCHEMA IF EXISTS public;
--
-- Name: public; Type: SCHEMA; Schema: -; Owner: pg_database_owner
--

CREATE SCHEMA public;


ALTER SCHEMA public OWNER TO pg_database_owner;

--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: pg_database_owner
--

COMMENT ON SCHEMA public IS 'standard public schema';


--
-- Name: check_temporal_constraint(); Type: FUNCTION; Schema: public; Owner: lifespan_user
--

CREATE FUNCTION public.check_temporal_constraint() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
        connection_span RECORD;
        constraint_type TEXT;
        span_state TEXT;
        is_placeholder BOOLEAN;
    BEGIN
        -- Get the connection span record
        SELECT * INTO connection_span FROM spans WHERE id = NEW.connection_span_id;

        -- Ensure the connection span exists
        IF connection_span IS NULL THEN
            RAISE EXCEPTION 'Connection span % not found', NEW.connection_span_id;
        END IF;

        -- Get the span state
        SELECT state INTO span_state FROM spans WHERE id = NEW.connection_span_id;

        -- Check if this is a placeholder
        is_placeholder := span_state = 'placeholder';

        -- Get the constraint type directly from connection_types
        SELECT ct.constraint_type INTO constraint_type 
        FROM connection_types ct
        WHERE ct.type = NEW.type_id;

        -- Ensure the connection type exists
        IF constraint_type IS NULL THEN
            RAISE EXCEPTION 'Connection type % not found', NEW.type_id;
        END IF;

        -- If not a placeholder, validate date precision hierarchy and ranges
        IF NOT is_placeholder THEN
            -- Start date validation
            IF connection_span.start_year IS NULL THEN
                RAISE EXCEPTION 'Start year is required for non-placeholder spans';
            END IF;

            IF connection_span.start_day IS NOT NULL AND connection_span.start_month IS NULL THEN
                RAISE EXCEPTION 'Start month is required when start day is provided';
            END IF;

            -- End date validation (if provided)
            IF connection_span.end_day IS NOT NULL AND connection_span.end_month IS NULL THEN
                RAISE EXCEPTION 'End month is required when end day is provided';
            END IF;

            IF connection_span.end_month IS NOT NULL AND connection_span.end_year IS NULL THEN
                RAISE EXCEPTION 'End year is required when end month is provided';
            END IF;

            -- Validate date ranges
            IF connection_span.start_month IS NOT NULL AND (connection_span.start_month < 1 OR connection_span.start_month > 12) THEN
                RAISE EXCEPTION 'Start month must be between 1 and 12';
            END IF;

            IF connection_span.start_day IS NOT NULL AND (connection_span.start_day < 1 OR connection_span.start_day > 31) THEN
                RAISE EXCEPTION 'Start day must be between 1 and 31';
            END IF;

            IF connection_span.end_year IS NOT NULL THEN
                IF connection_span.end_month IS NOT NULL AND (connection_span.end_month < 1 OR connection_span.end_month > 12) THEN
                    RAISE EXCEPTION 'End month must be between 1 and 12';
                END IF;

                IF connection_span.end_day IS NOT NULL AND (connection_span.end_day < 1 OR connection_span.end_day > 31) THEN
                    RAISE EXCEPTION 'End day must be between 1 and 31';
                END IF;

                -- Compare dates at the appropriate precision level
                IF connection_span.end_year < connection_span.start_year THEN
                    RAISE EXCEPTION 'End date cannot be before start date';
                END IF;

                IF connection_span.end_year = connection_span.start_year AND 
                   connection_span.end_month IS NOT NULL AND 
                   connection_span.start_month IS NOT NULL AND
                   connection_span.end_month < connection_span.start_month THEN
                    RAISE EXCEPTION 'End date cannot be before start date';
                END IF;

                IF connection_span.end_year = connection_span.start_year AND 
                   connection_span.end_month = connection_span.start_month AND 
                   connection_span.end_day IS NOT NULL AND
                   connection_span.start_day IS NOT NULL AND
                   connection_span.end_day < connection_span.start_day THEN
                    RAISE EXCEPTION 'End date cannot be before start date';
                END IF;
            END IF;
        ELSE
            -- For placeholders, we still validate precision hierarchy
            -- Start date precision validation (if any start components exist)
            IF connection_span.start_day IS NOT NULL AND connection_span.start_month IS NULL THEN
                RAISE EXCEPTION 'Start month is required when start day is provided';
            END IF;

            IF connection_span.start_month IS NOT NULL AND connection_span.start_year IS NULL THEN
                RAISE EXCEPTION 'Start year is required when start month is provided';
            END IF;

            -- End date precision validation (if any end components exist)
            IF connection_span.end_day IS NOT NULL AND connection_span.end_month IS NULL THEN
                RAISE EXCEPTION 'End month is required when end day is provided';
            END IF;

            IF connection_span.end_month IS NOT NULL AND connection_span.end_year IS NULL THEN
                RAISE EXCEPTION 'End year is required when end month is provided';
            END IF;

            -- For placeholders: allow end date without start date,
            -- but if start date exists, validate that end date isn't before it
            IF connection_span.start_year IS NOT NULL AND connection_span.end_year IS NOT NULL THEN
                -- Compare dates at the appropriate precision level
                IF connection_span.end_year < connection_span.start_year THEN
                    RAISE EXCEPTION 'End date cannot be before start date';
                END IF;

                IF connection_span.end_year = connection_span.start_year AND 
                   connection_span.end_month IS NOT NULL AND 
                   connection_span.start_month IS NOT NULL AND
                   connection_span.end_month < connection_span.start_month THEN
                    RAISE EXCEPTION 'End date cannot be before start date';
                END IF;

                IF connection_span.end_year = connection_span.start_year AND 
                   connection_span.end_month = connection_span.start_month AND 
                   connection_span.end_day IS NOT NULL AND
                   connection_span.start_day IS NOT NULL AND
                   connection_span.end_day < connection_span.start_day THEN
                    RAISE EXCEPTION 'End date cannot be before start date';
                END IF;
            END IF;
        END IF;

        -- Apply constraint-specific validation
        IF constraint_type = 'single' THEN
            IF EXISTS (
                SELECT 1 FROM connections
                WHERE parent_id = NEW.parent_id
                AND child_id = NEW.child_id
                AND type_id = NEW.type_id
                AND id IS DISTINCT FROM NEW.id
            ) THEN
                RAISE EXCEPTION 'Only one connection of this type is allowed between these spans';
            END IF;
        ELSIF constraint_type = 'non_overlapping' AND NOT is_placeholder THEN
            -- Only check overlaps for non-placeholder spans
            -- Check for overlapping dates with existing connections
            IF EXISTS (
                SELECT 1 FROM connections c
                JOIN spans s ON s.id = c.connection_span_id
                WHERE c.parent_id = NEW.parent_id
                AND c.child_id = NEW.child_id
                AND c.type_id = NEW.type_id
                AND c.id IS DISTINCT FROM NEW.id
                AND NOT (
                    -- s is the existing connection span
                    -- connection_span is the new span
                    -- No overlap if:
                    -- 1. New span ends before existing span starts
                    -- 2. New span starts after existing span ends
                    (connection_span.end_year IS NOT NULL AND s.start_year IS NOT NULL AND
                     (connection_span.end_year < s.start_year OR
                      (connection_span.end_year = s.start_year AND
                       connection_span.end_month IS NOT NULL AND s.start_month IS NOT NULL AND
                       connection_span.end_month < s.start_month) OR
                      (connection_span.end_year = s.start_year AND
                       connection_span.end_month = s.start_month AND
                       connection_span.end_day IS NOT NULL AND s.start_day IS NOT NULL AND
                       connection_span.end_day < s.start_day)))
                    OR
                    (s.end_year IS NOT NULL AND connection_span.start_year IS NOT NULL AND
                     (s.end_year < connection_span.start_year OR
                      (s.end_year = connection_span.start_year AND
                       s.end_month IS NOT NULL AND connection_span.start_month IS NOT NULL AND
                       s.end_month < connection_span.start_month) OR
                      (s.end_year = connection_span.start_year AND
                       s.end_month = connection_span.start_month AND
                       s.end_day IS NOT NULL AND connection_span.start_day IS NOT NULL AND
                       s.end_day < connection_span.start_day)))
                )
            ) THEN
                RAISE EXCEPTION 'Connection dates overlap with an existing connection';
            END IF;
        END IF;

        RETURN NEW;
    END;
    $$;


ALTER FUNCTION public.check_temporal_constraint() OWNER TO lifespan_user;

--
-- Name: sync_span_connections(); Type: FUNCTION; Schema: public; Owner: lifespan_user
--

CREATE FUNCTION public.sync_span_connections() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                -- Refresh the materialized view for affected spans
                REFRESH MATERIALIZED VIEW CONCURRENTLY span_connections;
                RETURN COALESCE(NEW, OLD);
            END;
            $$;


ALTER FUNCTION public.sync_span_connections() OWNER TO lifespan_user;

--
-- Name: update_connection_dates(); Type: FUNCTION; Schema: public; Owner: lifespan_user
--

CREATE FUNCTION public.update_connection_dates() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Update family connections
    IF NEW.type_id = 'person' THEN
        -- Update connections where this span is the child
        UPDATE spans s
        SET 
            start_year = NEW.start_year,
            start_month = NEW.start_month,
            start_day = NEW.start_day,
            start_precision = NEW.start_precision,
            end_year = CASE 
                WHEN NEW.end_year IS NOT NULL AND parent.end_year IS NOT NULL THEN
                    CASE WHEN NEW.end_year < parent.end_year THEN NEW.end_year ELSE parent.end_year END
                ELSE COALESCE(NEW.end_year, parent.end_year)
            END,
            end_month = CASE 
                WHEN NEW.end_year IS NOT NULL AND parent.end_year IS NOT NULL THEN
                    CASE WHEN NEW.end_year < parent.end_year THEN NEW.end_month ELSE parent.end_month END
                ELSE COALESCE(NEW.end_month, parent.end_month)
            END,
            end_day = CASE 
                WHEN NEW.end_year IS NOT NULL AND parent.end_year IS NOT NULL THEN
                    CASE WHEN NEW.end_year < parent.end_year THEN NEW.end_day ELSE parent.end_day END
                ELSE COALESCE(NEW.end_day, parent.end_day)
            END,
            end_precision = CASE 
                WHEN NEW.end_year IS NOT NULL AND parent.end_year IS NOT NULL THEN
                    CASE WHEN NEW.end_year < parent.end_year THEN NEW.end_precision ELSE parent.end_precision END
                ELSE COALESCE(NEW.end_precision, parent.end_precision)
            END
        FROM connections c
        JOIN spans parent ON c.parent_id = parent.id
        WHERE s.id = c.connection_span_id
        AND c.child_id = NEW.id
        AND c.type_id = 'family';

        -- Update connections where this span is the parent
        UPDATE spans s
        SET 
            end_year = CASE 
                WHEN NEW.end_year IS NOT NULL AND child.end_year IS NOT NULL THEN
                    CASE WHEN NEW.end_year < child.end_year THEN NEW.end_year ELSE child.end_year END
                ELSE COALESCE(NEW.end_year, child.end_year)
            END,
            end_month = CASE 
                WHEN NEW.end_year IS NOT NULL AND child.end_year IS NOT NULL THEN
                    CASE WHEN NEW.end_year < child.end_year THEN NEW.end_month ELSE child.end_month END
                ELSE COALESCE(NEW.end_month, child.end_month)
            END,
            end_day = CASE 
                WHEN NEW.end_year IS NOT NULL AND child.end_year IS NOT NULL THEN
                    CASE WHEN NEW.end_year < child.end_year THEN NEW.end_day ELSE child.end_day END
                ELSE COALESCE(NEW.end_day, child.end_day)
            END,
            end_precision = CASE 
                WHEN NEW.end_year IS NOT NULL AND child.end_year IS NOT NULL THEN
                    CASE WHEN NEW.end_year < child.end_year THEN NEW.end_precision ELSE child.end_precision END
                ELSE COALESCE(NEW.end_precision, child.end_precision)
            END
        FROM connections c
        JOIN spans child ON c.child_id = child.id
        WHERE s.id = c.connection_span_id
        AND c.parent_id = NEW.id
        AND c.type_id = 'family';
    END IF;

    -- Update membership connection dates
    IF NEW.type_id IN ('person', 'organisation', 'band') THEN
        -- Update connections where this span is the organization/band (child)
        IF NEW.type_id IN ('organisation', 'band') THEN
            UPDATE spans s
            SET 
                start_year = CASE 
                    WHEN s.start_year IS NULL AND NEW.start_year IS NOT NULL THEN NEW.start_year
                    ELSE s.start_year
                END,
                start_month = CASE 
                    WHEN s.start_year IS NULL AND NEW.start_year IS NOT NULL THEN NEW.start_month
                    ELSE s.start_month
                END,
                start_day = CASE 
                    WHEN s.start_year IS NULL AND NEW.start_year IS NOT NULL THEN NEW.start_day
                    ELSE s.start_day
                END,
                start_precision = CASE 
                    WHEN s.start_year IS NULL AND NEW.start_year IS NOT NULL THEN NEW.start_precision
                    ELSE s.start_precision
                END,
                end_year = CASE 
                    WHEN NEW.end_year IS NOT NULL AND (parent.end_year IS NULL OR parent.end_year > NEW.end_year) THEN NEW.end_year
                    ELSE s.end_year
                END,
                end_month = CASE 
                    WHEN NEW.end_year IS NOT NULL AND (parent.end_year IS NULL OR parent.end_year > NEW.end_year) THEN NEW.end_month
                    ELSE s.end_month
                END,
                end_day = CASE 
                    WHEN NEW.end_year IS NOT NULL AND (parent.end_year IS NULL OR parent.end_year > NEW.end_year) THEN NEW.end_day
                    ELSE s.end_day
                END,
                end_precision = CASE 
                    WHEN NEW.end_year IS NOT NULL AND (parent.end_year IS NULL OR parent.end_year > NEW.end_year) THEN NEW.end_precision
                    ELSE s.end_precision
                END
            FROM connections c
            JOIN spans parent ON c.parent_id = parent.id
            WHERE s.id = c.connection_span_id
            AND c.child_id = NEW.id
            AND c.type_id = 'membership';
        END IF;

        -- Update connections where this span is the person (parent)
        IF NEW.type_id = 'person' THEN
            UPDATE spans s
            SET 
                end_year = CASE 
                    WHEN NEW.end_year IS NOT NULL AND (child.end_year IS NULL OR child.end_year > NEW.end_year) THEN NEW.end_year
                    ELSE s.end_year
                END,
                end_month = CASE 
                    WHEN NEW.end_year IS NOT NULL AND (child.end_year IS NULL OR child.end_year > NEW.end_year) THEN NEW.end_month
                    ELSE s.end_month
                END,
                end_day = CASE 
                    WHEN NEW.end_year IS NOT NULL AND (child.end_year IS NULL OR child.end_year > NEW.end_year) THEN NEW.end_day
                    ELSE s.end_day
                END,
                end_precision = CASE 
                    WHEN NEW.end_year IS NOT NULL AND (child.end_year IS NULL OR child.end_year > NEW.end_year) THEN NEW.end_precision
                    ELSE s.end_precision
                END
            FROM connections c
            JOIN spans child ON c.child_id = child.id
            WHERE s.id = c.connection_span_id
            AND c.parent_id = NEW.id
            AND c.type_id = 'membership';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_connection_dates() OWNER TO lifespan_user;

--
-- Name: update_family_connection_dates(); Type: FUNCTION; Schema: public; Owner: lifespan_user
--

CREATE FUNCTION public.update_family_connection_dates() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        -- Only proceed if dates have changed
        IF (
            NEW.start_year IS DISTINCT FROM OLD.start_year OR
            NEW.start_month IS DISTINCT FROM OLD.start_month OR
            NEW.start_day IS DISTINCT FROM OLD.start_day OR
            NEW.end_year IS DISTINCT FROM OLD.end_year OR
            NEW.end_month IS DISTINCT FROM OLD.end_month OR
            NEW.end_day IS DISTINCT FROM OLD.end_day
        ) THEN
            -- Update connection spans where this span is the child
            UPDATE spans
            SET 
                start_year = NEW.start_year,
                start_month = NEW.start_month,
                start_day = NEW.start_day,
                start_precision = NEW.start_precision
            FROM connections
            WHERE 
                spans.id = connections.connection_span_id
                AND connections.child_id = NEW.id
                AND connections.type_id = 'family';

            -- Update connection spans where this span is the parent and has an end date
            -- (only update end date if parent dies before child)
            IF NEW.end_year IS NOT NULL THEN
                UPDATE spans
                SET 
                    end_year = NEW.end_year,
                    end_month = NEW.end_month,
                    end_day = NEW.end_day,
                    end_precision = NEW.end_precision
                FROM connections c1
                WHERE 
                    spans.id = c1.connection_span_id
                    AND c1.parent_id = NEW.id
                    AND c1.type_id = 'family'
                    AND (
                        -- Only set parent's death as end date if child is still alive
                        -- or died after parent
                        SELECT COUNT(*)
                        FROM connections c2
                        JOIN spans child ON child.id = c2.child_id
                        WHERE 
                            c2.id = c1.id
                            AND (
                                child.end_year IS NULL
                                OR child.end_year > NEW.end_year
                                OR (
                                    child.end_year = NEW.end_year
                                    AND (
                                        child.end_month IS NULL
                                        OR child.end_month > NEW.end_month
                                        OR (
                                            child.end_month = NEW.end_month
                                            AND (
                                                child.end_day IS NULL
                                                OR child.end_day > NEW.end_day
                                            )
                                        )
                                    )
                                )
                            )
                    ) > 0;
            END IF;

            -- Update connection spans where this span is the child and has an end date
            -- (only update end date if child dies before parent)
            IF NEW.end_year IS NOT NULL THEN
                UPDATE spans
                SET 
                    end_year = NEW.end_year,
                    end_month = NEW.end_month,
                    end_day = NEW.end_day,
                    end_precision = NEW.end_precision
                FROM connections c1
                WHERE 
                    spans.id = c1.connection_span_id
                    AND c1.child_id = NEW.id
                    AND c1.type_id = 'family'
                    AND (
                        -- Only set child's death as end date if parent is still alive
                        -- or died after child
                        SELECT COUNT(*)
                        FROM connections c2
                        JOIN spans parent ON parent.id = c2.parent_id
                        WHERE 
                            c2.id = c1.id
                            AND (
                                parent.end_year IS NULL
                                OR parent.end_year > NEW.end_year
                                OR (
                                    parent.end_year = NEW.end_year
                                    AND (
                                        parent.end_month IS NULL
                                        OR parent.end_month > NEW.end_month
                                        OR (
                                            parent.end_month = NEW.end_month
                                            AND (
                                                parent.end_day IS NULL
                                                OR parent.end_day > NEW.end_day
                                            )
                                        )
                                    )
                                )
                            )
                    ) > 0;
            END IF;
        END IF;

        RETURN NEW;
    END;
    $$;


ALTER FUNCTION public.update_family_connection_dates() OWNER TO lifespan_user;

--
-- Name: update_place_hierarchy(); Type: FUNCTION; Schema: public; Owner: lifespan_user
--

CREATE FUNCTION public.update_place_hierarchy() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        -- If this is a new place or the parent_id has changed
        IF TG_OP = 'INSERT' OR (TG_OP = 'UPDATE' AND OLD.parent_id IS DISTINCT FROM NEW.parent_id) THEN
            -- If this is a root place (no parent)
            IF NEW.parent_id IS NULL THEN
                NEW.root_id := NEW.id;
            ELSE
                -- Get the root_id from the parent
                SELECT root_id INTO NEW.root_id
                FROM spans
                WHERE id = NEW.parent_id;
            END IF;
        END IF;
        RETURN NEW;
    END;
    $$;


ALTER FUNCTION public.update_place_hierarchy() OWNER TO lifespan_user;

--
-- Name: validate_place(); Type: FUNCTION; Schema: public; Owner: lifespan_user
--

CREATE FUNCTION public.validate_place() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        -- Ensure location is set for places
        IF NEW.type_id = 'place' AND NEW.location IS NULL THEN
            RAISE EXCEPTION 'Location is required for places';
        END IF;

        -- Ensure boundary is valid if provided
        IF NEW.type_id = 'place' AND NEW.boundary IS NOT NULL THEN
            IF NOT ST_IsValid(NEW.boundary) THEN
                RAISE EXCEPTION 'Invalid boundary geometry';
            END IF;
        END IF;

        -- Ensure location is within boundary if both are provided
        IF NEW.type_id = 'place' AND NEW.location IS NOT NULL AND NEW.boundary IS NOT NULL THEN
            IF NOT ST_Contains(NEW.boundary, NEW.location) THEN
                RAISE EXCEPTION 'Location must be within boundary';
            END IF;
        END IF;

        RETURN NEW;
    END;
    $$;


ALTER FUNCTION public.validate_place() OWNER TO lifespan_user;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: connection_types; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.connection_types (
    type character varying(255) NOT NULL,
    forward_predicate character varying(255) NOT NULL,
    forward_description character varying(255) NOT NULL,
    inverse_predicate character varying(255) NOT NULL,
    inverse_description character varying(255) NOT NULL,
    allowed_span_types json,
    constraint_type character varying(255) DEFAULT 'single'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.connection_types OWNER TO lifespan_user;

--
-- Name: connections; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.connections (
    id uuid NOT NULL,
    parent_id uuid NOT NULL,
    child_id uuid NOT NULL,
    type_id character varying(255) NOT NULL,
    connection_span_id uuid NOT NULL,
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.connections OWNER TO lifespan_user;

--
-- Name: connections_spo; Type: VIEW; Schema: public; Owner: lifespan_user
--

CREATE VIEW public.connections_spo AS
 SELECT connections.id,
    connections.parent_id AS subject_id,
    connections.child_id AS object_id,
    connections.type_id,
    connections.connection_span_id,
    connections.created_at,
    connections.updated_at
   FROM public.connections;


ALTER TABLE public.connections_spo OWNER TO lifespan_user;

--
-- Name: invitation_codes; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.invitation_codes (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    used boolean DEFAULT false NOT NULL,
    used_at timestamp(0) without time zone,
    used_by character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.invitation_codes OWNER TO lifespan_user;

--
-- Name: invitation_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: lifespan_user
--

CREATE SEQUENCE public.invitation_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.invitation_codes_id_seq OWNER TO lifespan_user;

--
-- Name: invitation_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: lifespan_user
--

ALTER SEQUENCE public.invitation_codes_id_seq OWNED BY public.invitation_codes.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO lifespan_user;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: lifespan_user
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.migrations_id_seq OWNER TO lifespan_user;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: lifespan_user
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO lifespan_user;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: lifespan_user
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.personal_access_tokens_id_seq OWNER TO lifespan_user;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: lifespan_user
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: spans; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.spans (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    type_id character varying(255) NOT NULL,
    is_personal_span boolean DEFAULT false NOT NULL,
    parent_id uuid,
    root_id uuid,
    start_year integer,
    start_month integer,
    start_day integer,
    end_year integer,
    end_month integer,
    end_day integer,
    start_precision character varying(255) DEFAULT 'year'::character varying NOT NULL,
    end_precision character varying(255) DEFAULT 'year'::character varying NOT NULL,
    state character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    description text,
    notes text,
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    sources jsonb,
    permissions integer DEFAULT 420 NOT NULL,
    permission_mode character varying(255) DEFAULT 'own'::character varying NOT NULL,
    access_level character varying(255) DEFAULT 'private'::character varying NOT NULL,
    owner_id uuid NOT NULL,
    updater_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT spans_access_level_check CHECK (((access_level)::text = ANY (ARRAY[('private'::character varying)::text, ('shared'::character varying)::text, ('public'::character varying)::text])))
);


ALTER TABLE public.spans OWNER TO lifespan_user;

--
-- Name: span_connections; Type: MATERIALIZED VIEW; Schema: public; Owner: lifespan_user
--

CREATE MATERIALIZED VIEW public.span_connections AS
 SELECT s.id AS span_id,
    COALESCE(jsonb_agg(jsonb_build_object('id', c.id, 'type', c.type_id, 'connected_span_id',
        CASE
            WHEN (c.parent_id = s.id) THEN c.child_id
            ELSE c.parent_id
        END, 'role',
        CASE
            WHEN (c.parent_id = s.id) THEN 'parent'::text
            ELSE 'child'::text
        END, 'connection_span_id', c.connection_span_id, 'metadata', COALESCE(c.metadata, '{}'::jsonb))) FILTER (WHERE (c.id IS NOT NULL)), '[]'::jsonb) AS connections
   FROM (public.spans s
     LEFT JOIN public.connections c ON (((s.id = c.parent_id) OR (s.id = c.child_id))))
  GROUP BY s.id
  WITH NO DATA;


ALTER TABLE public.span_connections OWNER TO lifespan_user;

--
-- Name: span_permissions; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.span_permissions (
    id uuid NOT NULL,
    span_id uuid NOT NULL,
    user_id uuid NOT NULL,
    group_id uuid,
    permission_type character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT span_permissions_permission_type_check CHECK (((permission_type)::text = ANY (ARRAY[('view'::character varying)::text, ('edit'::character varying)::text])))
);


ALTER TABLE public.span_permissions OWNER TO lifespan_user;

--
-- Name: span_types; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.span_types (
    type_id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.span_types OWNER TO lifespan_user;

--
-- Name: telescope_entries; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.telescope_entries (
    sequence bigint NOT NULL,
    uuid uuid NOT NULL,
    batch_id uuid NOT NULL,
    family_hash character varying(255),
    should_display_on_index boolean DEFAULT true NOT NULL,
    type character varying(20) NOT NULL,
    content text NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.telescope_entries OWNER TO lifespan_user;

--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE; Schema: public; Owner: lifespan_user
--

CREATE SEQUENCE public.telescope_entries_sequence_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.telescope_entries_sequence_seq OWNER TO lifespan_user;

--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: lifespan_user
--

ALTER SEQUENCE public.telescope_entries_sequence_seq OWNED BY public.telescope_entries.sequence;


--
-- Name: telescope_entries_tags; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.telescope_entries_tags (
    entry_uuid uuid NOT NULL,
    tag character varying(255) NOT NULL
);


ALTER TABLE public.telescope_entries_tags OWNER TO lifespan_user;

--
-- Name: telescope_monitoring; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.telescope_monitoring (
    tag character varying(255) NOT NULL
);


ALTER TABLE public.telescope_monitoring OWNER TO lifespan_user;

--
-- Name: user_spans; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.user_spans (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    span_id uuid NOT NULL,
    access_level character varying(255) DEFAULT 'viewer'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.user_spans OWNER TO lifespan_user;

--
-- Name: users; Type: TABLE; Schema: public; Owner: lifespan_user
--

CREATE TABLE public.users (
    id uuid NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    personal_span_id uuid,
    is_admin boolean DEFAULT false NOT NULL,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.users OWNER TO lifespan_user;

--
-- Name: invitation_codes id; Type: DEFAULT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.invitation_codes ALTER COLUMN id SET DEFAULT nextval('public.invitation_codes_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: telescope_entries sequence; Type: DEFAULT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.telescope_entries ALTER COLUMN sequence SET DEFAULT nextval('public.telescope_entries_sequence_seq'::regclass);


--
-- Data for Name: connection_types; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--

INSERT INTO public.connection_types VALUES ('created', 'created', 'Created', 'created by', 'Created by', '{"parent":["thing"],"child":["person","organisation","band"]}', 'single', '2025-03-26 17:42:27', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('contains', 'contains', 'Contains', 'contained in', 'Contained in', '{"parent":["thing"],"child":["thing"]}', 'non_overlapping', '2025-03-26 17:42:27', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('attendance', 'attended', 'Attended', 'was attended by', 'Was attended by', NULL, 'non_overlapping', '2025-03-26 17:42:26', '2025-03-26 17:42:26');
INSERT INTO public.connection_types VALUES ('ownership', 'owned', 'Owned', 'was owned by', 'Was owned by', NULL, 'non_overlapping', '2025-03-26 17:42:26', '2025-03-26 17:42:26');
INSERT INTO public.connection_types VALUES ('travel', 'traveled to', 'Traveled to', 'was visited by', 'Was visited by', '{"parent":["person"],"child":["place"]}', 'non_overlapping', '2025-03-26 17:42:26', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('participation', 'participated in', 'Participated in', 'had participant', 'Had as a participant', '{"parent":["person","organisation"],"child":["event"]}', 'non_overlapping', '2025-03-26 17:42:26', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('employment', 'worked at', 'Worked at', 'employed', 'Employed', '{"parent":["person"],"child":["organisation"]}', 'non_overlapping', '2025-03-26 17:42:26', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('education', 'studied at', 'Studied at', 'educated', 'Educated', '{"parent":["person"],"child":["organisation"]}', 'non_overlapping', '2025-03-26 17:42:26', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('residence', 'lived in', 'Lived in', 'was home to', 'Was home to', '{"parent":["person"],"child":["place"]}', 'non_overlapping', '2025-03-26 17:42:26', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('membership', 'was member of', 'Was member of', 'had member', 'Had member', '{"parent":["person"],"child":["organisation","band"]}', 'non_overlapping', '2025-03-26 17:42:26', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('family', 'is family of', 'Is a family member of', 'is family of', 'Is a family member of', '{"parent":["person"],"child":["person"]}', 'single', '2025-03-26 17:42:26', '2025-03-26 17:42:27');
INSERT INTO public.connection_types VALUES ('relationship', 'has relationship with', 'Has a relationship with', 'has relationship with', 'Has a relationship with', '{"parent":["person"],"child":["person"]}', 'single', '2025-03-26 17:42:26', '2025-03-26 17:42:27');


--
-- Data for Name: connections; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--

INSERT INTO public.connections VALUES ('9e86a2fd-3c90-45cc-bed7-49305180229e', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a2fd-1f7b-451f-a14e-3893c1097d76', 'family', '9e86a2fd-371b-4f81-879f-c6f52eb4dcd3', '{}', '2025-03-26 17:42:53', '2025-03-26 17:42:53');
INSERT INTO public.connections VALUES ('9e86a2ff-2b13-486e-923a-46f9ac63ae1e', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-2141-4020-a294-d445d5bb9aca', 'education', '9e86a2ff-24e8-4a76-930e-1170d34865d7', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-439a-43dd-ad7f-acaa18ef7ec4', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-366b-46b7-9d94-7f73a64dad5a', 'education', '9e86a2ff-3d5a-4c88-a25d-09beb1cf97c5', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-55e7-4299-bf34-04f09805a832', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-4d6e-468b-a0f1-048a56f99c57', 'education', '9e86a2ff-520d-4bd3-845b-13a87530a118', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-709a-43f4-8d5f-ee42ae1dee21', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-6956-4521-8597-4070b52b60f1', 'education', '9e86a2ff-6bf3-477f-b8a5-e58ba2c134d6', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-7979-4546-a38b-8841b923678b', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-7663-4036-aef0-e484f9cd3df1', 'employment', '9e86a2ff-778c-47d1-adef-0156b9528efd', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-82c0-4b11-8b67-56070e445655', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-7eae-4cd1-b65f-9f79b0a41da9', 'employment', '9e86a2ff-805a-4d1e-b1e1-ace1be155665', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-8bd4-4492-afcd-97d95f12cfdb', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-87df-4c7e-8868-ce71214f97b7', 'residence', '9e86a2ff-899d-49b2-bf43-0ab5e177f9cc', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-9385-4524-93c7-34dda1443a45', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-8ff6-461c-a58c-e71f097e7719', 'residence', '9e86a2ff-914f-46e8-b2fa-f6b9a4a92766', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-9a27-45b0-8ada-e81a5c4e5194', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-97a4-481d-b589-b5a8de29e817', 'residence', '9e86a2ff-98af-4386-a375-86604d4ae3d3', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-9ec8-4a7b-95b4-15b3edb9f99e', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-9c3e-46c2-95eb-eecc800e79e3', 'residence', '9e86a2ff-9d4f-4f97-b500-9302ab0dc1fa', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-a35f-480f-a21a-aa799ff53d20', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-a0c2-4681-9ab8-8ffbc59dc756', 'residence', '9e86a2ff-a1dc-493a-9de0-a54db5489b56', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-aa2e-443c-b378-c48b2e21b948', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-a5e2-4f73-a894-470bbd0566b9', 'residence', '9e86a2ff-a761-4a41-917b-fbea519b7531', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a2ff-af8e-49b0-bbd3-e73ca3e642ff', '9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', '9e86a2ff-ace5-45d4-b13b-188466075baa', 'relationship', '9e86a2ff-ae05-4367-a2fa-697b855fa34a', '{}', '2025-03-26 17:42:54', '2025-03-26 17:42:54');
INSERT INTO public.connections VALUES ('9e86a300-9840-4590-8449-95ba0e925a1f', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-921a-43e0-a973-ae67e2dd798c', 'education', '9e86a300-9441-46fc-a69c-f36b1690fa8c', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-a484-4894-b512-d3b228032f9f', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-9fe8-4d47-9f0a-39d7ff96b798', 'education', '9e86a300-a18f-4a3c-a280-e9163188a37e', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-af2b-4f96-920e-3a1e18319f97', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-a97a-4583-8d3f-1e8b84809ed4', 'employment', '9e86a300-ac9a-4a02-aa3b-80ee01bca10d', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-b845-4036-9f05-061abfc93fa1', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-b44a-4f84-a861-983785bbb0b6', 'employment', '9e86a300-b5c5-46f5-b653-bbcf75c79340', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-c28c-4574-992f-04af698a6dec', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-bcff-4134-98a7-a3fc6bafea4f', 'employment', '9e86a300-bf7a-45ce-a028-f6727220489f', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-cad4-4655-965f-34e8153068d6', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-c702-452c-9dea-2b393a9cefda', 'employment', '9e86a300-c85a-4eb3-870a-2498ef0ccbb9', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-d524-4c0e-93f4-706755a845e8', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-d1a5-435b-a1d4-65820bbc87a2', 'employment', '9e86a300-d30a-41b1-a803-b95268e12f3e', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-dcc7-4e93-ba15-04d89dbae177', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-d949-4109-9f90-c332f79e2f00', 'employment', '9e86a300-da9d-4899-9115-08ec9c441420', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-e3b0-470a-b538-8247c42d7754', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-e01a-4af3-9cc8-c27d29478406', 'residence', '9e86a300-e180-4d19-bb25-8414589d190a', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-ebf7-46dc-9caf-e487802c9101', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-e8a1-4440-b355-e8627b6aa81e', 'residence', '9e86a300-ea09-4321-a458-5ee42720a782', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-f397-4e64-9fd6-66193c5b5ec1', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-ef9d-45e8-941c-dca040e2c386', 'residence', '9e86a300-f103-456c-94ec-35e91690f0d7', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a300-fb2c-4691-8467-f259d27ecb71', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-f7f8-4f5b-8aac-3477c692aaa3', 'residence', '9e86a300-f93d-45c7-96d3-26cc43daa0cb', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a301-01d8-43f8-83aa-3c1f463ef673', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a300-fea9-4152-8322-eb500eca41dc', 'residence', '9e86a300-ffe4-4e29-b4cc-2c9f828f74d4', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a301-0929-486b-ac2b-d8e3a5ae5b75', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a301-0630-43a4-a4b9-4aa44b3e0be1', 'relationship', '9e86a301-076d-431c-ad8d-fa160ed92577', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a301-0fcd-4c01-83bb-6e60014b267c', '9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', '9e86a301-0cc1-4bab-bd4c-82dccc4289f3', 'relationship', '9e86a301-0e14-4b80-8f38-b207528b6699', '{}', '2025-03-26 17:42:55', '2025-03-26 17:42:55');
INSERT INTO public.connections VALUES ('9e86a2fe-2b68-4fe3-8b65-4fcc02c9be79', '9e86a2fe-2109-419c-88e2-cb1e41ccd40b', '9e86a2fe-1cb9-4b1f-a3e2-105ff9540135', 'family', '9e86a2fe-250d-4c70-bb1e-106bfae13411', '{}', '2025-03-26 17:42:53', '2025-03-26 17:42:58');
INSERT INTO public.connections VALUES ('9e86a306-f334-4786-b0a9-9a5a0c713e54', '9e86a306-e94d-483f-be6f-6e73fd49e6bf', '9e86a306-e569-4694-b81c-cd79e6bd409f', 'family', '9e86a306-ecab-4e4f-bc78-6dc7ddb39c47', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-087b-45ca-bf9f-29b136b59262', '9e86a306-ffaf-4a34-b054-b57a97010baa', '9e86a306-e569-4694-b81c-cd79e6bd409f', 'family', '9e86a307-03f9-42af-be45-d7c1a9a52f75', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-16c0-4144-a834-d064e4ab0fae', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-107c-4570-9db9-87ea3e17042d', 'family', '9e86a307-12ee-42e8-be78-0ea75ea11191', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-2102-4b21-9a7f-64a0a1578569', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-1bcb-41d5-91b6-e7304974c846', 'family', '9e86a307-1e0a-4302-8415-48bc4f99d5d2', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-2a27-4916-bdb1-b513295a36ed', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-2554-45b9-98e2-16525d7314cf', 'education', '9e86a307-274e-4bef-9e53-3c4e7daa5eb6', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-331e-4b38-bce2-2b854e7afded', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-2e8b-4431-94a1-981b20d6294c', 'education', '9e86a307-3048-4c86-af18-16a1f9bb6f2b', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-3b38-434e-af80-842a3b44a315', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-3730-4e34-b4ee-0de18354b029', 'education', '9e86a307-38ca-4dc9-a3a4-0e69888a575e', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-42dd-4960-b8ff-29963c77dfd0', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-3f0f-42a7-a74a-ad4a1a1535b2', 'education', '9e86a307-4089-4b15-b6c0-d4324d6627a2', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-4a6d-4c51-8b51-71cd9aec1648', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-46be-42f5-87fb-62f0731ec4fd', 'employment', '9e86a307-4837-4cc3-bc53-d0c37b06db21', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-53c6-4629-830e-065eb2bd7958', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-500d-4bcc-902d-d952dd40dde0', 'residence', '9e86a307-5198-463d-a0d4-63eab62510b9', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-5a50-4eec-83fd-e430b8a49c0b', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-5734-48b8-a675-9844a38e60e2', 'residence', '9e86a307-5876-495e-957b-2ccac25d1468', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a307-60c1-453b-bc81-3834976fa0ee', '9e86a306-e569-4694-b81c-cd79e6bd409f', '9e86a307-5db0-4757-96f3-fb0899af8a8f', 'relationship', '9e86a307-5ef8-4fd6-a9bc-4d71f9d09089', '{}', '2025-03-26 17:42:59', '2025-03-26 17:42:59');
INSERT INTO public.connections VALUES ('9e86a30a-4ad1-438c-9c7e-2c6e698038be', '9e86a30a-3b45-4452-b5bf-9a6aa91b3ae9', '9e86a30a-3e96-4b9b-81c1-eb720b76f11d', 'employment', '9e86a30a-421d-4eea-ab2e-8cf41a5d520b', '{}', '2025-03-26 17:43:01', '2025-03-26 17:43:01');
INSERT INTO public.connections VALUES ('9e86a30b-4702-448d-bd87-1110e36e63b2', '9e86a30b-3c3a-4bc9-82bf-eed780b945b9', '9e86a30b-37fd-4d68-a2bd-6b386834057d', 'family', '9e86a30b-4077-4733-831f-d37bd00a8d13', '{}', '2025-03-26 17:43:02', '2025-03-26 17:43:02');
INSERT INTO public.connections VALUES ('9e86a30b-5a67-489f-a9ed-7d1025fd2c58', '9e86a30b-52b6-4281-8639-21fe10c3e2d8', '9e86a30b-37fd-4d68-a2bd-6b386834057d', 'family', '9e86a30b-55c3-49fd-878a-bf444e462619', '{}', '2025-03-26 17:43:02', '2025-03-26 17:43:02');
INSERT INTO public.connections VALUES ('9e86a30b-68de-4018-9b05-0dd569ac7555', '9e86a30b-37fd-4d68-a2bd-6b386834057d', '9e86a30b-6241-4090-8958-6454b30cea03', 'education', '9e86a30b-64f4-43c3-a86f-30467d9006e9', '{}', '2025-03-26 17:43:02', '2025-03-26 17:43:02');
INSERT INTO public.connections VALUES ('9e86a30b-7468-4b82-bc73-0e350da1e4ae', '9e86a30b-37fd-4d68-a2bd-6b386834057d', '9e86a30b-6ec3-456c-a2a6-fc31a6dc04b7', 'residence', '9e86a30b-7136-4394-b7c6-29d3c9e4e2f6', '{}', '2025-03-26 17:43:02', '2025-03-26 17:43:02');
INSERT INTO public.connections VALUES ('9e86a30b-7f28-4f40-b1db-0c36c3af5f81', '9e86a30b-37fd-4d68-a2bd-6b386834057d', '9e86a30b-7a00-44ec-b4d6-255894d5922b', 'residence', '9e86a30b-7c26-4223-b0f5-868704b6d4e8', '{}', '2025-03-26 17:43:02', '2025-03-26 17:43:02');
INSERT INTO public.connections VALUES ('9e86a30b-88a3-4265-8bae-4259a75c94a6', '9e86a30b-37fd-4d68-a2bd-6b386834057d', '9e86a30b-845a-4797-9d5c-98f81bcd02bb', 'residence', '9e86a30b-8623-443d-96c9-9c3ef1cd1e72', '{}', '2025-03-26 17:43:02', '2025-03-26 17:43:02');
INSERT INTO public.connections VALUES ('9e86a309-3b87-42bc-9bd9-94b6407c17a1', '9e86a309-31fc-4284-8600-d3ea311a89dc', '9e86a309-15d9-4252-a7f5-19573d14d193', 'family', '9e86a309-3602-4589-9683-90ea1ee95d2b', '{}', '2025-03-26 17:43:00', '2025-03-26 17:43:03');
INSERT INTO public.connections VALUES ('9e86a30f-5344-47e7-a4a9-1b473375aeeb', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a30f-3b47-4cb9-89b6-317c113b4f05', 'family', '9e86a30f-505b-4534-836c-6a8d11261032', '{}', '2025-03-26 17:43:04', '2025-03-26 17:43:04');
INSERT INTO public.connections VALUES ('9e86a310-4192-409f-81d0-4b29c946e94f', '9e86a310-38f7-4a3f-a296-f9cc974ba12c', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', 'family', '9e86a310-3c7e-4bdf-9700-8c031bd34ecb', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-50ad-49f9-959d-4a8d0fb19cd9', '9e86a310-4b25-47a1-af05-2cd884a1e92f', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', 'family', '9e86a310-4d5c-4bf0-a641-4e0254a3549e', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a30c-a49b-4d47-8865-c784693d5ee6', '9e86a309-31fc-4284-8600-d3ea311a89dc', '9e86a30c-9cf7-4533-b8b1-08bf0e11c8e0', 'family', '9e86a30c-9fd3-4974-8734-951062222661', '{}', '2025-03-26 17:43:03', '2025-03-26 17:43:15');
INSERT INTO public.connections VALUES ('9e86a2fd-2d09-4bc9-a2d7-94f2c63af800', '9e86a2fd-2311-40d2-ac40-e6761c9b4504', '9e86a2fd-1f7b-451f-a14e-3893c1097d76', 'family', '9e86a2fd-2639-4874-8e77-ab9e60c9252c', '{}', '2025-03-26 17:42:53', '2025-03-26 17:43:16');
INSERT INTO public.connections VALUES ('9e86a309-2506-4b3f-b9bc-96a70f24d5a3', '9e86a309-19a4-4fdb-8c27-1c506cc50052', '9e86a309-15d9-4252-a7f5-19573d14d193', 'family', '9e86a309-1db4-4db9-a325-9cd7be6416cf', '{}', '2025-03-26 17:43:00', '2025-03-26 17:43:23');
INSERT INTO public.connections VALUES ('9e86a303-093f-44ab-ab03-22cb54d9dc5b', '9e86a302-fd8c-4496-9453-5dad183b6c66', '9e86a302-fa32-4e77-a850-a461d0b3bc4b', 'family', '9e86a303-022e-44ed-8536-f4cd4a723199', '{}', '2025-03-26 17:42:56', '2025-03-26 17:43:29');
INSERT INTO public.connections VALUES ('9e86a30c-7df6-4050-b18b-943bd0354565', '9e86a30c-7589-43b3-accc-a37751edb5ba', '9e86a309-31fc-4284-8600-d3ea311a89dc', 'family', '9e86a30c-78ea-4842-823d-4239a1cc6b61', '{}', '2025-03-26 17:43:03', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a309-5157-48f1-a1d5-a4f4025bc0c8', '9e86a309-15d9-4252-a7f5-19573d14d193', '9e86a309-44ef-4098-9452-983f2809bebe', 'family', '9e86a309-4ad2-4c20-8781-ac7874ee5210', '{}', '2025-03-26 17:43:01', '2025-03-26 17:43:36');
INSERT INTO public.connections VALUES ('9e86a303-1ba8-4aeb-813f-82c0e1171869', '9e86a303-1313-4862-8281-d4f0dd4ff5fd', '9e86a302-fa32-4e77-a850-a461d0b3bc4b', 'family', '9e86a303-1658-4036-afd8-d6f15426df52', '{}', '2025-03-26 17:42:56', '2025-03-26 17:43:43');
INSERT INTO public.connections VALUES ('9e86a301-fb87-4ce6-be8b-ad7b4d84887b', '9e86a301-ed9e-4a34-9349-005a8a05fc2c', '9e86a301-f1bb-45dc-a729-e7ce5c688c3e', 'family', '9e86a301-f4f4-42fe-9baa-9696171dcf83', '{}', '2025-03-26 17:42:56', '2025-03-26 17:43:43');
INSERT INTO public.connections VALUES ('9e86a2fe-38eb-49ec-97c9-3e769f62c399', '9e86a2fe-334f-46dc-acfb-79ac5489643d', '9e86a2fe-1cb9-4b1f-a3e2-105ff9540135', 'family', '9e86a2fe-353e-4443-a062-3c7b975c8c2a', '{}', '2025-03-26 17:42:53', '2025-03-26 17:43:51');
INSERT INTO public.connections VALUES ('9e86a310-5db9-4986-8164-767fca0a5204', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-580b-411a-9485-6c8107db65ee', 'family', '9e86a310-5a4e-4a68-bc45-428814b306f2', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-696c-4916-80af-7af22acd59d6', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-6442-47c1-bf41-c07da95b7124', 'family', '9e86a310-6676-4795-a7da-391c905b6f78', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-73a2-45bd-aff5-31076f2249a7', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-6f13-41ea-b122-d91871a5b4c2', 'family', '9e86a310-70ec-40a2-9cdc-dc3a4489eb75', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-7d45-4508-92d4-f238236b13af', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-7911-46c1-94e0-7b08f0a5c2c8', 'education', '9e86a310-7ade-4c15-9f9b-9a957e84c13c', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-866b-4ff4-bf63-118cd84acd7b', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-824c-4e37-9ba1-0c22c24fd541', 'education', '9e86a310-83e7-417d-9e8e-42067d7949b7', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-8eb9-4010-a327-84abd207c9ac', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-8ae4-48a7-94cb-4cc383af2b83', 'employment', '9e86a310-8c6f-4e75-8a13-45f9a501f9a0', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-9a9c-40db-99b0-6ad3ad80a4ae', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-9703-4c65-86e3-aafac24d8514', 'employment', '9e86a310-986d-4805-be01-0653315d668a', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-a23a-4c17-b659-cb8109a88f46', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-9eba-43b3-a0e3-d7fc84fa15e1', 'residence', '9e86a310-a01c-48b1-8dd6-3bae61923142', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a310-a994-4b75-9876-d2883327b886', '9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', '9e86a310-a631-434b-985a-a254fb9ed460', 'relationship', '9e86a310-a7a2-40eb-be30-81509972b674', '{}', '2025-03-26 17:43:05', '2025-03-26 17:43:05');
INSERT INTO public.connections VALUES ('9e86a311-98cb-4220-a9de-be32c601e94b', '9e86a311-8ded-47bb-b52f-66332e4a5b66', '9e86a311-8b42-490a-b0c5-0af83de1276c', 'family', '9e86a311-9134-454d-85aa-23a7e88d1dc6', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a311-afe3-42a7-8348-2b48148eea45', '9e86a311-a662-4a53-abf7-c00209cf2c3b', '9e86a311-8b42-490a-b0c5-0af83de1276c', 'family', '9e86a311-aa0e-4ee3-964b-38472ba1c1b1', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a311-c0a4-45db-8935-56bdc517d438', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a311-b8ee-4504-bc1d-f9e3be47cc04', 'family', '9e86a311-bb97-4484-89e3-5512248b1343', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a311-cedd-4c88-9b1b-7cfafe510720', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a311-c8bd-4d80-b40b-ff69ed9f7a53', 'family', '9e86a311-cae7-4095-a108-585bb5dcd095', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a311-da0e-4357-9426-8c4fa2273223', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a311-d528-414b-80ab-2de9dcdf1b73', 'education', '9e86a311-d720-4fcd-ac25-fa5f40f6653e', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a311-e422-452f-9a11-5da14f8bf7f6', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a311-dfbc-4140-b974-f061c264f17b', 'employment', '9e86a311-e186-4bae-9288-2c87e72b5f20', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a311-edda-4f08-8fde-713b312b8524', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a311-e9a2-4549-a72e-bace81e512c0', 'residence', '9e86a311-eb75-42b0-9ac7-81b38ed15afd', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a311-f78b-4975-8533-2fd2f8e025cb', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a311-f3b3-4a1b-9cc3-f02c91c4112a', 'residence', '9e86a311-f54b-4b49-b246-dfcc3033eda3', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a312-0062-4cb2-88e8-b953671208a9', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a311-fc84-487c-9bb5-bf1e220e3634', 'residence', '9e86a311-fe07-4f32-aed9-8e2329ddd96e', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a312-088b-463f-ad36-512d3a192e94', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a312-04f0-49b3-abf2-4daea65041cc', 'relationship', '9e86a312-066e-4a3a-9adc-9fb04db3f3e0', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a312-1019-4d5d-81bf-b1ab67e9912a', '9e86a311-8b42-490a-b0c5-0af83de1276c', '9e86a312-0cb0-427a-a350-2f09d146659f', 'relationship', '9e86a312-0e22-4af1-ac8e-ba391b5ae58a', '{}', '2025-03-26 17:43:06', '2025-03-26 17:43:06');
INSERT INTO public.connections VALUES ('9e86a312-fcb1-44ac-bb35-822509047044', '9e86a312-f3a1-4c10-9dd8-34f41b1db58a', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', 'family', '9e86a312-f6ed-45d4-aa20-7dbdc3d0dc58', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-1067-464b-ba91-e77374e4f72b', '9e86a313-0900-4beb-9b9c-996f6cf7d469', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', 'family', '9e86a313-0bec-4ded-b793-2a4f84b6ac8d', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-1fbf-47e4-bb45-a1b0f93100f2', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-196c-492e-96c5-b66759c416ea', 'family', '9e86a313-1c3c-4591-b4fa-6f0d69d0faf7', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-2c5a-4413-b0bc-99fa880177eb', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-269b-4aab-b6ae-869547a35307', 'family', '9e86a313-28e5-4094-a223-83cbeca5920d', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-37fc-47e3-913c-12fc874a3875', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-3306-4aeb-9432-738a5d5c8d0b', 'family', '9e86a313-3521-4e8d-b71e-9fb53889b647', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-431e-430e-831a-6c0705c9acce', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-3e5a-42da-a67a-f6d1c2708dee', 'family', '9e86a313-4066-4a98-a0c2-a73edd28e8b0', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-4c8b-4b00-a6ce-d3eac7952d22', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-4866-4a59-a08c-889c9fe48609', 'family', '9e86a313-4a18-4f3c-b888-bff8c4b88ecd', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-5583-4709-8ec4-427eb90c8323', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-5194-48dd-8eca-64862cc7f7ca', 'education', '9e86a313-5330-4263-9c2e-d6fcfba441e7', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-5e34-48d9-b7b1-6c399fd07680', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-5a2e-44b4-9d6c-d2aaeac530f2', 'education', '9e86a313-5b99-4a19-9e41-033987dd81ca', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-652f-45f0-8e74-a660853a6440', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a307-46be-42f5-87fb-62f0731ec4fd', 'employment', '9e86a313-630c-4659-8fa9-2e09844861d3', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-6c12-4916-9dad-f51f09002d63', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-68bb-4af4-8e01-3c2b95e3836b', 'employment', '9e86a313-6a17-4cba-ba90-895e3d3eeaf1', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-7355-4f0f-95d2-3ca0bc981ca4', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-7020-4cac-9261-2b03b74e26b0', 'residence', '9e86a313-7175-4086-b676-08ee928d8284', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-7ab5-4e8f-af37-c421f7cf8094', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-774d-4837-9fa3-653a0ae95fe0', 'residence', '9e86a313-789b-4f53-95bc-2a82a96e6780', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-81fe-4c94-87e4-17a6ffb48bdc', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-7e96-4f36-b0a3-c7afff537fd2', 'relationship', '9e86a313-7fe6-445b-948c-0501f8ca9d3a', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-8913-4bb6-a10c-60b6cdc894ce', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-85d8-4130-be66-39526648b1a3', 'relationship', '9e86a313-8725-48bc-90f4-9c7638a3d369', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a313-9001-477b-8295-0ef59cf37557', '9e86a312-f09c-4fa7-a635-c88a9ab0b7be', '9e86a313-8cd2-4a42-b2a0-3a1959401491', 'relationship', '9e86a313-8e24-46a7-ad2a-dafddc616f3e', '{}', '2025-03-26 17:43:07', '2025-03-26 17:43:07');
INSERT INTO public.connections VALUES ('9e86a302-0ee3-4de3-b5e2-1f3b196197f6', '9e86a301-ed9e-4a34-9349-005a8a05fc2c', '9e86a302-0711-4cbc-afc8-9466c9ea8345', 'family', '9e86a302-0a71-4859-8a1b-32fffff948bf', '{}', '2025-03-26 17:42:56', '2025-03-26 17:43:08');
INSERT INTO public.connections VALUES ('9e86a316-6097-4392-b6c5-2ea90890eee0', '9e86a316-51c9-412d-8562-2f40af154fd2', '9e86a316-4e33-433d-8ffd-5779d0451f8a', 'family', '9e86a316-553c-48c7-997a-6773ac0be4de', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-78f3-4a76-a9ff-cf07e90fb558', '9e86a316-6e86-4727-9801-ccd7a5a1021d', '9e86a316-4e33-433d-8ffd-5779d0451f8a', 'family', '9e86a316-71f1-461b-a0d3-0263038a8d44', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-8cb8-4da8-95a0-5937b6e4d756', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a316-83ee-4c1d-bb66-decfc3d209c2', 'family', '9e86a316-8736-489c-8b06-72309c6577b9', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-9c9f-4746-8499-c62808c1f0e7', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a316-95b4-4333-9ab9-5fbbaaf58103', 'education', '9e86a316-9861-4c04-9e9f-ee929ad633be', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-a957-4355-a16b-e946dd62b108', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a316-a3be-49ca-bc73-2144e7a865e4', 'employment', '9e86a316-a601-4ba8-912b-74b9ba192988', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-b32b-40f5-b697-38f7cd6e8308', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a311-dfbc-4140-b974-f061c264f17b', 'employment', '9e86a316-b049-472c-9160-f655678dddef', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-bdad-42f7-b8be-8e960dc7fccd', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a316-b8ee-46be-bda8-126d9140c341', 'residence', '9e86a316-baf6-4aef-a335-755d7e41accf', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-c7a8-4fcc-a86a-2c6274e37b89', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a316-c302-4d1d-8a67-ac0a992fe4de', 'residence', '9e86a316-c4ed-42f7-88e6-e10a12644041', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-d0b2-4936-a153-226a57283482', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a316-cc90-4712-a4e1-0f30c4cae453', 'relationship', '9e86a316-ce55-4bf4-bfec-f230b26bfba6', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a316-d947-4e7f-9da9-314e97443283', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a316-d586-40e4-b1f6-8cde3ee4162f', 'relationship', '9e86a316-d729-46f0-a801-314d5bb827a7', '{}', '2025-03-26 17:43:09', '2025-03-26 17:43:09');
INSERT INTO public.connections VALUES ('9e86a317-b91c-4331-b8f1-e9f24bb5d16a', '9e86a317-b1c7-4273-9a13-3ee211b159f1', '9e86a317-afe8-4d0f-ac79-24e20d44a019', 'family', '9e86a317-b3f9-408d-8f59-71f7a61c8142', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a317-c815-48bb-a1ec-f4038f1a1aeb', '9e86a317-c30e-4d5b-8277-0e87b77668be', '9e86a317-afe8-4d0f-ac79-24e20d44a019', 'family', '9e86a317-c49c-4eb4-a88a-dacc19492a84', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a317-d6cd-4d3b-89a5-cd4392865ddb', '9e86a317-afe8-4d0f-ac79-24e20d44a019', '9e86a317-d1eb-4234-9889-018c05cf4006', 'education', '9e86a317-d40c-4ba8-a0d3-b6a3b73d801c', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a317-dcdc-4146-b315-c07401c1f242', '9e86a317-afe8-4d0f-ac79-24e20d44a019', '9e86a317-da26-4d16-a2c4-e6b2b5c5b0c1', 'education', '9e86a317-db4b-4d94-b0eb-19105c627beb', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a317-e2b1-41e6-9a7d-43884a18df3a', '9e86a317-afe8-4d0f-ac79-24e20d44a019', '9e86a317-dfea-42b1-ae4f-2820b989ffb2', 'employment', '9e86a317-e0f6-42e9-8fa1-7debb4ccaf29', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a317-e87d-4558-af75-5214772c2c7a', '9e86a317-afe8-4d0f-ac79-24e20d44a019', '9e86a317-e5b8-4208-92fb-dcb53456ccc6', 'employment', '9e86a317-e6d6-45d7-9c28-2e8bd8777269', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a317-ee66-4560-832a-e772a84398d5', '9e86a317-afe8-4d0f-ac79-24e20d44a019', '9e86a317-eb86-45dd-8afb-cd3f9ec46279', 'residence', '9e86a317-ec9c-4947-adbe-d12fbccb8b1e', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a317-f426-4d9b-bf63-fc7ebdc7f912', '9e86a317-afe8-4d0f-ac79-24e20d44a019', '9e86a317-f178-4217-a571-17ed19f15cd4', 'residence', '9e86a317-f287-4b0e-a694-ba71de15943c', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a317-fa28-4eaf-85f0-6d78edd337fb', '9e86a317-afe8-4d0f-ac79-24e20d44a019', '9e86a317-f76f-461e-95a2-40c5b3d511bd', 'relationship', '9e86a317-f891-40aa-a07b-d5f19805b7c4', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a318-0a5c-4b80-a1db-8269d6f36dad', '9e86a317-afe8-4d0f-ac79-24e20d44a019', '9e86a318-0777-49dc-832b-12703413c44e', 'relationship', '9e86a318-08ba-4c02-b3a7-daa72a2ace30', '{}', '2025-03-26 17:43:10', '2025-03-26 17:43:10');
INSERT INTO public.connections VALUES ('9e86a319-12de-49d8-8e93-2db473217e8d', '9e86a318-f04a-4bbf-b6fd-8eec9634e544', '9e86a319-092c-45e2-896e-a6bc1d6dbbc9', 'family', '9e86a319-0c9c-4d4a-956b-0d43fa6b16d9', '{}', '2025-03-26 17:43:11', '2025-03-26 17:43:11');
INSERT INTO public.connections VALUES ('9e86a31a-db0c-4497-b73b-c3f33bec435e', '9e86a31a-cae1-4264-b731-af3e998d6239', '9e86a31a-cea7-4029-98ec-271812eaaa9e', 'employment', '9e86a31a-d284-40a8-86cb-68013b926a1b', '{}', '2025-03-26 17:43:12', '2025-03-26 17:43:12');
INSERT INTO public.connections VALUES ('9e86a31d-99ec-41e0-aef5-ec2497021593', '9e86a30c-9cf7-4533-b8b1-08bf0e11c8e0', '9e86a31d-76c3-476d-b2e2-8bc9f01232bc', 'family', '9e86a31d-962f-4df0-be17-204073ae9701', '{}', '2025-03-26 17:43:14', '2025-03-26 17:43:15');
INSERT INTO public.connections VALUES ('9e86a31f-6563-46b3-b4e5-9c2a199fe8a8', '9e86a309-19a4-4fdb-8c27-1c506cc50052', '9e86a30c-9cf7-4533-b8b1-08bf0e11c8e0', 'family', '9e86a31f-622a-41fa-951e-ba6b324e8bab', '{}', '2025-03-26 17:43:15', '2025-03-26 17:43:23');
INSERT INTO public.connections VALUES ('9e86a318-fc5e-4c31-a78d-dfec2dcf73ce', '9e86a318-f04a-4bbf-b6fd-8eec9634e544', '9e86a318-f3a8-4eab-a2d9-9d99d3b5b0fd', 'family', '9e86a318-f5fd-497e-a856-d4a963c0f597', '{}', '2025-03-26 17:43:11', '2025-03-26 17:43:44');
INSERT INTO public.connections VALUES ('9e86a315-6989-4eaa-8304-d3ab58d5c418', '9e86a315-6045-4320-83b3-b40f42e09013', '9e86a302-0711-4cbc-afc8-9466c9ea8345', 'family', '9e86a315-6512-4d8f-819f-0fc7f0f6670f', '{}', '2025-03-26 17:43:08', '2025-03-26 17:43:45');
INSERT INTO public.connections VALUES ('9e86a31d-87b7-4699-92c4-d4e8513bedfb', '9e86a31d-7aad-45b8-b6cb-c48b6c48e944', '9e86a31d-76c3-476d-b2e2-8bc9f01232bc', 'family', '9e86a31d-7ebc-4979-8787-bf24cc0c199e', '{}', '2025-03-26 17:43:14', '2025-03-26 17:43:45');
INSERT INTO public.connections VALUES ('9e86a30f-4822-4f30-b778-689f38a8bd60', '9e86a2fd-2311-40d2-ac40-e6761c9b4504', '9e86a30f-3b47-4cb9-89b6-317c113b4f05', 'family', '9e86a30f-40af-4ad1-9bce-800217686eaf', '{}', '2025-03-26 17:43:04', '2025-03-26 17:43:16');
INSERT INTO public.connections VALUES ('9e86a320-7ff7-484a-ac8d-c05d074e653c', '9e86a320-7777-4470-862f-acf279ec3b93', '9e86a2fd-2311-40d2-ac40-e6761c9b4504', 'family', '9e86a320-7b9d-405b-86ad-a381f9fd06a0', '{}', '2025-03-26 17:43:16', '2025-03-26 17:43:16');
INSERT INTO public.connections VALUES ('9e86a320-8d94-43bd-8963-b9b5961ec779', '9e86a320-87b2-4b39-8238-2a987744136e', '9e86a2fd-2311-40d2-ac40-e6761c9b4504', 'family', '9e86a320-8a17-4c5a-8967-0a4acd0a17e9', '{}', '2025-03-26 17:43:16', '2025-03-26 17:43:16');
INSERT INTO public.connections VALUES ('9e86a321-7e60-4397-80a7-b8ddd8fb279c', '9e86a321-75ba-4b57-a2f7-760feaed8279', '9e86a321-7306-4937-b66d-4b45f6f2ad0d', 'family', '9e86a321-77b0-4071-9cff-dd51ed0e1dac', '{}', '2025-03-26 17:43:16', '2025-03-26 17:43:16');
INSERT INTO public.connections VALUES ('9e86a321-9642-46a0-8672-896732cfbfe4', '9e86a321-8d42-4480-a07f-d4b98070f73a', '9e86a321-7306-4937-b66d-4b45f6f2ad0d', 'family', '9e86a321-91ac-4a22-a277-31939c88774d', '{}', '2025-03-26 17:43:16', '2025-03-26 17:43:16');
INSERT INTO public.connections VALUES ('9e86a321-a3bb-44cb-afca-bdeca1835df7', '9e86a321-7306-4937-b66d-4b45f6f2ad0d', '9e86a321-9f12-4384-89bd-15a14126ac8e', 'education', '9e86a321-a112-4197-8644-0bd9adb864dd', '{}', '2025-03-26 17:43:16', '2025-03-26 17:43:16');
INSERT INTO public.connections VALUES ('9e86a321-ab72-4bc7-a2be-6a1007d23539', '9e86a321-7306-4937-b66d-4b45f6f2ad0d', '9e86a321-a872-4fd7-afbc-0286865dcd0b', 'employment', '9e86a321-a9aa-415f-92d0-b7e98dc81acd', '{}', '2025-03-26 17:43:16', '2025-03-26 17:43:16');
INSERT INTO public.connections VALUES ('9e86a321-b358-40e3-be35-2a3255d3c74c', '9e86a321-7306-4937-b66d-4b45f6f2ad0d', '9e86a321-af7b-4b3e-beb7-b5923841b052', 'employment', '9e86a321-b116-45c8-b658-8c6d7359f784', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a321-bd1d-442a-9887-412270f007f0', '9e86a321-7306-4937-b66d-4b45f6f2ad0d', '9e86a321-b861-45ff-bb21-47359683d1a0', 'residence', '9e86a321-b9e9-4922-93a8-c49fcfa1c3b5', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a321-c5d8-471a-b05c-31140385c097', '9e86a321-7306-4937-b66d-4b45f6f2ad0d', '9e86a321-c1e2-46e1-9c1b-668c02a5684b', 'residence', '9e86a321-c328-47f5-bed7-30e89dcad2f0', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-a966-4d97-809d-d7c2cc0dfbe2', '9e86a322-a2f7-4a04-bddf-92b3744d7043', '9e86a322-a07f-405f-832a-b42d8cea8f96', 'family', '9e86a322-a4a8-4627-bfd6-26162172b335', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-bc5f-4ffe-834f-cf7ff2a93c9b', '9e86a322-b51a-4314-81e1-39d6c73afc53', '9e86a322-a07f-405f-832a-b42d8cea8f96', 'family', '9e86a322-b7f9-4949-bbbf-ccaa4fb2fc33', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-d050-4631-86a3-3fa5f3b21d7f', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a319-092c-45e2-896e-a6bc1d6dbbc9', 'family', '9e86a322-ce0a-4b60-a988-8bbbd2af62b6', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-d6e6-4d51-98d4-59719c97e08c', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a322-d3f1-43f3-842c-39de2a47d2fb', 'education', '9e86a322-d50f-4840-87cc-84ea0a340348', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-de63-4369-8a9c-de089838581a', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a322-db27-4bd9-8a08-db4a1d05ffd0', 'education', '9e86a322-dc75-4726-80a0-94b13c68e15e', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-e54a-4a72-b171-c077c334ba56', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a322-e223-4f50-9aae-59145ecb28b9', 'education', '9e86a322-e35f-4de7-8b30-6ff67f666c9b', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-ec04-4f71-9d21-11eb9ab9f689', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a322-e8f3-4bfe-8973-ba31a66ba7fb', 'education', '9e86a322-ea25-4fb0-9d00-9f52b6fcc818', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-f2c6-4135-b44a-cef839b88ebe', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a322-ef8e-44af-813e-31f21247cdd2', 'residence', '9e86a322-f0e4-4601-9b84-707764619eff', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a322-f986-4c48-81e3-b2d561fea6dc', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a322-f66d-4a1a-875e-2a6f5737956a', 'residence', '9e86a322-f7b2-4ece-b305-df8fd9e94333', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a323-007b-4323-a1ec-522ebe897249', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a322-fd5f-4914-8e7c-6db0a9470e8b', 'residence', '9e86a322-feb8-4c9d-a7d9-6a6907a52dba', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a323-0696-4d01-90bf-c9c4bad85676', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a318-f04a-4bbf-b6fd-8eec9634e544', 'relationship', '9e86a323-04bd-45ff-accd-91f9abfca045', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:17');
INSERT INTO public.connections VALUES ('9e86a323-ebf0-4457-994f-e20c7cdc4f9a', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a323-e539-44c4-a4ec-7c7cda3db11e', 'education', '9e86a323-e760-40bc-a3c3-addbee27b26f', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a323-ff49-4cbc-86a3-60a6b77bfbc1', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a323-f8f9-40e9-9352-6f09003eae37', 'education', '9e86a323-fbb2-41e0-81db-f42a8b60571d', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-0d67-4a24-9187-fa532765094e', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-086d-455d-a849-e0b0b5ddb86f', 'education', '9e86a324-0a8d-4602-a41a-549797278f8c', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-17b1-4840-bfad-870f0d018789', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-13a5-4364-9f37-d2396f811139', 'education', '9e86a324-1519-42a3-a12f-81be4b68059c', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-214f-44a2-9c2d-8dbecc0d751d', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-1d42-4f0f-b897-9af32c50106e', 'employment', '9e86a324-1f1b-43ac-ad88-ccbb58245cd2', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-2b02-45a8-b88c-39c210acebab', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-274c-4eb9-bf8b-5b32406cbf37', 'employment', '9e86a324-28b3-4213-9f31-5639cad2d8a5', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-34b0-4d04-b817-cb0cabf1843b', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-30e5-4147-a7dd-7d9f999d8266', 'employment', '9e86a324-3269-4ab0-8df4-90c1326db69e', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-3e54-4dd9-99a9-dffcf0a6a9d3', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-3a80-40f3-938d-385e6072a986', 'employment', '9e86a324-3bf5-49a6-998f-e87fbb22eed2', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-49f9-4d0d-8a13-0523078e3f20', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-4669-426d-95e4-bed3694e14dd', 'residence', '9e86a324-47d8-4bfb-accf-1f41d007f670', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-5322-46bf-b87a-361e63c6c526', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-4f9a-4018-a267-6c752da21380', 'residence', '9e86a324-511d-4cf7-afa3-dcf2ad4b5086', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-5ba1-49a8-a8eb-dc58bb6386d3', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-5859-4d34-8737-48a12f2fc39c', 'residence', '9e86a324-59b1-49ad-a88b-5e4859252837', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-63ef-42fc-bad6-0bab01a4ef01', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-60a8-4731-90f5-97a1d7b8bca0', 'relationship', '9e86a324-6208-4caf-a13a-122c133601d5', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a324-6c4f-4631-acd9-b765420aaaf0', '9e86a323-e2d6-4496-8564-9da166be8e79', '9e86a324-68cf-4d01-a36c-4569f0ddbd6a', 'relationship', '9e86a324-6a48-48dc-95ad-a3b4108d2737', '{}', '2025-03-26 17:43:18', '2025-03-26 17:43:18');
INSERT INTO public.connections VALUES ('9e86a326-677b-49b4-a720-6d02463a4912', '9e86a326-5bce-4164-ad09-1dabcd561cca', '9e86a326-580e-4718-bf84-2342e6dda3b0', 'family', '9e86a326-5f95-418a-a5a1-a062b051a984', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-7fb7-4473-9c14-a72f445122f6', '9e86a326-76f8-43e4-bfa4-95b68b864bfc', '9e86a326-580e-4718-bf84-2342e6dda3b0', 'family', '9e86a326-7a1a-4f77-b3c4-57930dbec868', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-a24f-4faf-9fd9-df1320506ef3', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a326-9841-4d62-bfc5-f366297d5431', 'family', '9e86a326-9c22-43ed-aed0-b517faeb356a', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-b555-4919-9964-23d1d57c6d1b', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a326-ad43-473e-bff0-ecce4bd96fb2', 'family', '9e86a326-afd5-4ac1-82c4-ca022d6eced5', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-c12d-4e0b-aa3f-4055fa15746f', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a326-bd46-41e3-ba3e-1e4c4818b897', 'education', '9e86a326-bed3-48a8-a912-ffeaea3d0fd2', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-c95c-4596-9fdd-5f50bd3cc903', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a326-c636-4c78-b8cf-53a01b724973', 'education', '9e86a326-c773-4261-a0eb-22bd27dbc200', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-d2ac-4604-b6da-fad3f73b3bd7', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a326-cf85-427c-a25f-6aca150aaee2', 'education', '9e86a326-d0c1-4b66-8b33-fae6b17c1623', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-db32-43c4-a08f-1a468beea03a', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a316-a3be-49ca-bc73-2144e7a865e4', 'employment', '9e86a326-d94d-4e8e-a840-75700a36a88e', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-e341-42e2-a27b-a169bc66e077', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a311-dfbc-4140-b974-f061c264f17b', 'employment', '9e86a326-e127-4323-acdf-c0ad50c4e967', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-ebcd-4b28-b102-29da1efa6df7', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a326-e87e-4b8d-be32-e8030e27c954', 'residence', '9e86a326-e9e6-4758-b0f3-d46f68ab97a6', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-f33c-4451-9183-2307f1d73b88', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a30b-845a-4797-9d5c-98f81bcd02bb', 'residence', '9e86a326-f145-48cb-9010-92c7b5cf8fec', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a326-fb16-4882-9ea6-2b64c8ddb211', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a326-f7d5-4fa1-8bf4-8edf497aa744', 'relationship', '9e86a326-f92f-4eb0-99db-eb50fd96a6b9', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a327-028c-4401-825b-bb514822f784', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a326-ff82-4ea5-b9d1-4992fdbce034', 'relationship', '9e86a327-00c3-43b0-a24f-2d1d457c1f10', '{}', '2025-03-26 17:43:20', '2025-03-26 17:43:20');
INSERT INTO public.connections VALUES ('9e86a325-7303-4282-8ebc-68d8f5285d61', '9e86a325-6add-4436-8489-13870be94e31', '9e86a325-4c22-41e0-b9cc-52fb8dd1d108', 'family', '9e86a325-6e8f-4bd5-81c3-93900750ccee', '{}', '2025-03-26 17:43:19', '2025-03-26 17:43:21');
INSERT INTO public.connections VALUES ('9e86a328-132e-49fc-a5e0-95e0ead02ffd', '9e86a325-6add-4436-8489-13870be94e31', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', 'family', '9e86a328-0fde-4ea0-95ae-b94384460611', '{}', '2025-03-26 17:43:21', '2025-03-26 17:43:21');
INSERT INTO public.connections VALUES ('9e86a329-1efe-448e-a52d-b4b6f1a45147', '9e86a329-155c-4ea4-8148-0af80c776db6', '9e86a329-1219-41b2-bff8-797e7fba52c3', 'family', '9e86a329-1901-4cd8-a227-565ce0c1c391', '{}', '2025-03-26 17:43:21', '2025-03-26 17:43:21');
INSERT INTO public.connections VALUES ('9e86a329-3780-4717-8787-e9bd61bab165', '9e86a329-2fe0-4aa5-addd-fe9e969223cb', '9e86a329-1219-41b2-bff8-797e7fba52c3', 'family', '9e86a329-3348-4700-892e-de2497ad3c9b', '{}', '2025-03-26 17:43:21', '2025-03-26 17:43:21');
INSERT INTO public.connections VALUES ('9e86a329-4787-4996-a4b7-fc1a7da6e84c', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-41fb-4773-99bb-b9beac591389', 'family', '9e86a329-4402-4d41-8fc2-4bc8eba799dc', '{}', '2025-03-26 17:43:21', '2025-03-26 17:43:21');
INSERT INTO public.connections VALUES ('9e86a329-5535-4578-a291-607104b0bc32', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-4fdf-46e8-b8d8-9480957ae319', 'education', '9e86a329-520e-4762-9939-ec2081b149f6', '{}', '2025-03-26 17:43:22', '2025-03-26 17:43:22');
INSERT INTO public.connections VALUES ('9e86a329-610c-4354-97cc-48fa074958b0', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-5c88-4f14-85e3-b152e69549d4', 'education', '9e86a329-5e46-4fe8-9e9f-d027e5cab58a', '{}', '2025-03-26 17:43:22', '2025-03-26 17:43:22');
INSERT INTO public.connections VALUES ('9e86a329-6b71-4210-9aa1-b0b1459f3058', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-67af-47f8-a9b8-6783ee61c77d', 'employment', '9e86a329-6936-4fa2-be55-880cdf684a2d', '{}', '2025-03-26 17:43:22', '2025-03-26 17:43:22');
INSERT INTO public.connections VALUES ('9e86a329-7709-4371-b9d2-2ef948ba9712', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-731f-4478-a77e-ab86b60dc0f1', 'residence', '9e86a329-74c1-4825-905e-4dc6a959bb5e', '{}', '2025-03-26 17:43:22', '2025-03-26 17:43:22');
INSERT INTO public.connections VALUES ('9e86a329-8022-4a7f-b050-fe07d1fd12b7', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-7ca4-4844-b0e7-bfb6303db915', 'residence', '9e86a329-7e0e-4a7c-a8fe-a7006d524022', '{}', '2025-03-26 17:43:22', '2025-03-26 17:43:22');
INSERT INTO public.connections VALUES ('9e86a325-5971-446e-80a6-ba197f1cd65f', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a325-4c22-41e0-b9cc-52fb8dd1d108', 'family', '9e86a325-52ed-4c2c-877a-9091dcc85c30', '{}', '2025-03-26 17:43:19', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a328-3193-4867-9057-cbe5c9429df2', '9e86a325-6add-4436-8489-13870be94e31', '9e86a302-fd8c-4496-9453-5dad183b6c66', 'family', '9e86a328-2f69-451d-a919-daee35ea8161', '{}', '2025-03-26 17:43:21', '2025-03-26 17:43:29');
INSERT INTO public.connections VALUES ('9e86a327-f5fb-4b35-9881-e80ea368ec2d', '9e86a30c-7589-43b3-accc-a37751edb5ba', '9e86a325-6add-4436-8489-13870be94e31', 'family', '9e86a327-f102-481d-8982-4aaf99a77842', '{}', '2025-03-26 17:43:21', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a31f-732b-4f13-87f7-45ff6428f475', '9e86a30c-9cf7-4533-b8b1-08bf0e11c8e0', '9e86a31f-6eb7-4799-90c4-9dc1bad51cfd', 'family', '9e86a31f-7081-4c06-8a4b-ade50c987ea3', '{}', '2025-03-26 17:43:15', '2025-03-26 17:43:42');
INSERT INTO public.connections VALUES ('9e86a322-c7af-4419-bfa4-f510615c00a7', '9e86a322-a07f-405f-832a-b42d8cea8f96', '9e86a318-f3a8-4eab-a2d9-9d99d3b5b0fd', 'family', '9e86a322-c49d-439d-8541-7fdfb9bfc71d', '{}', '2025-03-26 17:43:17', '2025-03-26 17:43:44');
INSERT INTO public.connections VALUES ('9e86a328-1e3a-49d3-8eec-79030e91810f', '9e86a325-6add-4436-8489-13870be94e31', '9e86a315-6045-4320-83b3-b40f42e09013', 'family', '9e86a328-1b65-464d-b49e-c9d93b9f71c7', '{}', '2025-03-26 17:43:21', '2025-03-26 17:43:45');
INSERT INTO public.connections VALUES ('9e86a328-2786-4507-bc65-0e6ee997d8ce', '9e86a325-6add-4436-8489-13870be94e31', '9e86a2fe-334f-46dc-acfb-79ac5489643d', 'family', '9e86a328-251d-4063-a3af-ada17eb219ba', '{}', '2025-03-26 17:43:21', '2025-03-26 17:43:51');
INSERT INTO public.connections VALUES ('9e86a329-8909-4e02-b022-65470111c86e', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-8572-43f6-9907-f8126d427504', 'residence', '9e86a329-86e9-4f8d-9480-e9618ad377e4', '{}', '2025-03-26 17:43:22', '2025-03-26 17:43:22');
INSERT INTO public.connections VALUES ('9e86a329-91d0-421c-99e5-366fb4aaeca5', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-8e70-452a-8981-1448a7a55e3b', 'relationship', '9e86a329-8fd9-42fc-b973-0f7a9f354313', '{}', '2025-03-26 17:43:22', '2025-03-26 17:43:22');
INSERT INTO public.connections VALUES ('9e86a329-99aa-4b69-a7a9-516767689cc4', '9e86a329-1219-41b2-bff8-797e7fba52c3', '9e86a329-968e-4605-9c0d-1697ab0fc442', 'relationship', '9e86a329-97db-4251-ad77-b30faf914a2a', '{}', '2025-03-26 17:43:22', '2025-03-26 17:43:22');
INSERT INTO public.connections VALUES ('9e86a32d-5588-4a76-acb1-f4f706f580e7', '9e86a32d-4ae8-4da7-a7da-718a65644942', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', 'family', '9e86a32d-4eee-4ecf-be4c-b816ef086adb', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-6f57-413e-bcce-8cead2a96976', '9e86a32d-6761-4ad1-8655-c5181e2e4cb8', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', 'family', '9e86a32d-6ac6-44ca-8162-7c2e6736e6a0', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-81ae-4f2d-b2ef-497b1d7cb9b6', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-7b99-4d34-bf9e-9a032bceeeb6', 'education', '9e86a32d-7e04-4364-b8f9-e5c01217b362', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-8edf-4468-a80a-4fbe230aad72', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-8a67-4eb0-8e3a-ab0d8c5896ff', 'education', '9e86a32d-8c46-4d3c-8a9e-ae5c1c79f282', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-9a73-49af-8665-a59051d495c9', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-9632-4903-b3fb-f8a4e5a2d775', 'employment', '9e86a32d-97ee-4d16-92f9-adbfe266dd8a', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-a43d-4ccf-80f7-234766a38430', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-a0b7-42b1-bedc-40eb7e5360fd', 'employment', '9e86a32d-a21e-4911-a195-9f12c9182ed4', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-ad63-4a13-98d2-5ed1f4fcf7fd', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-a9de-4cb5-8d11-155fd6c81484', 'employment', '9e86a32d-ab2c-47b1-ba9b-e6c95834de90', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-b612-4c19-8020-b202c65ac46d', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-b2c9-45ab-a9e3-341272ad5c8f', 'employment', '9e86a32d-b41e-4a31-b375-ff0dc2cedee9', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-be7c-4f87-a65c-e94db451fbc8', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-bb46-410c-a1d1-cd3af58f53e9', 'residence', '9e86a32d-bc9c-4a33-91de-62b59c4d96e9', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-c653-42c9-95d0-37f4f5a1ea59', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-c34e-4e98-8b9a-d13e6b6eaefa', 'residence', '9e86a32d-c485-4469-8032-684d573e44c7', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-ce77-4a08-90bd-b3a75caf93e9', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-cb39-4736-a8d5-6f0eb504a638', 'residence', '9e86a32d-cc8c-4f27-96fb-1d95b3c6c258', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-d599-4649-86d4-2d127cbec15d', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a321-c1e2-46e1-9c1b-668c02a5684b', 'residence', '9e86a32d-d3c9-4dcb-a67c-eca1d33811a7', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32d-dd4f-4640-b719-4f6ea7fd1f44', '9e86a32d-475f-4568-bf5c-091c3ca8ef3c', '9e86a32d-da39-44b5-bf77-43613897d9a4', 'relationship', '9e86a32d-db84-4071-91fd-b7aed7fc1e12', '{}', '2025-03-26 17:43:24', '2025-03-26 17:43:24');
INSERT INTO public.connections VALUES ('9e86a32e-cc10-4042-b0f0-946070da51b1', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32e-c3dc-44eb-814c-e66eba89a4ff', 'education', '9e86a32e-c6b9-41d7-b6ba-d7c844727974', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32e-e4ee-434d-8320-1c8fb4d7ac64', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32e-de86-4143-a9d5-3b624fe15b1d', 'education', '9e86a32e-e119-41a7-9610-842d211aae80', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32e-f4ac-4de5-8f6c-f4b5852eb1ef', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32e-ef67-44ac-b6b9-93cbf0a47aeb', 'education', '9e86a32e-f18d-454b-8c14-b09f57c73c44', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-0126-4b94-a0df-ec66a4aae252', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32e-fce0-45ba-a073-e7babce3fed4', 'education', '9e86a32e-fea4-4ee5-8147-6be628e26b58', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-0b52-4364-ae11-097f723b12f2', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32f-07c5-4038-89d3-d090714ba84b', 'education', '9e86a32f-0926-4782-bbc3-0d07586de6d0', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-14db-4f00-8bc2-1844ac2f01c2', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32f-1151-44ef-bd12-70b2ada57c2b', 'employment', '9e86a32f-12bd-42df-801b-c6bd1635f4e8', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-1d00-4d58-82b5-32c2bfddf8f1', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a329-67af-47f8-a9b8-6783ee61c77d', 'employment', '9e86a32f-1afe-44ac-96fd-48e8a5458ed0', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-257c-4bfe-9fb9-d0334f3c821f', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32f-224b-40d1-9620-b0685d71f78a', 'residence', '9e86a32f-2399-43db-91b6-c946b808340e', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-2dd5-4d35-93d3-904c68e2350f', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32f-2a8d-45cd-9050-ae9ebd3a9e65', 'residence', '9e86a32f-2bd8-432a-b67a-c8f3e6322e30', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-357e-42fc-8a9b-92474473c1fc', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a307-5734-48b8-a675-9844a38e60e2', 'residence', '9e86a32f-33a3-45be-825f-d49382001f51', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-3db1-4575-abd0-d35bd8b2b352', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32f-3a99-4dd5-9210-d9ce624392f2', 'residence', '9e86a32f-3be3-410d-9fda-936e487b7fbb', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-4493-403a-a6ec-798606fb5f43', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a30b-845a-4797-9d5c-98f81bcd02bb', 'residence', '9e86a32f-42ea-4c38-af13-7df08178e536', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-4be3-4d52-8e3d-ab553e1297bf', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32f-4922-41bd-bc53-6afe43e4814f', 'relationship', '9e86a32f-4a4d-4cc6-a9b8-9f9d6aac9d8d', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a32f-533a-4b04-821d-514b9b0ffa7d', '9e86a32e-c0f6-4d06-949d-a44c1e9ac789', '9e86a32f-5060-449f-9885-3a77044d01ae', 'relationship', '9e86a32f-5189-4fc0-b0d7-1a2e883a3cf0', '{}', '2025-03-26 17:43:25', '2025-03-26 17:43:25');
INSERT INTO public.connections VALUES ('9e86a330-39a8-4d21-b148-efee1727781f', '9e86a330-3059-48ef-9911-fd5ea1c88713', '9e86a307-3730-4e34-b4ee-0de18354b029', 'education', '9e86a330-34db-46a0-8c1a-af22df602122', '{}', '2025-03-26 17:43:26', '2025-03-26 17:43:26');
INSERT INTO public.connections VALUES ('9e86a330-4e89-4ec2-ab58-2380fb886e1a', '9e86a330-3059-48ef-9911-fd5ea1c88713', '9e86a329-67af-47f8-a9b8-6783ee61c77d', 'employment', '9e86a330-4b0b-467a-b114-908fb229e83a', '{}', '2025-03-26 17:43:26', '2025-03-26 17:43:26');
INSERT INTO public.connections VALUES ('9e86a330-5dd6-43c5-8017-cd8f974d946c', '9e86a330-3059-48ef-9911-fd5ea1c88713', '9e86a330-58f7-4497-8d30-1933bc81b1e5', 'relationship', '9e86a330-5b53-4108-b1a5-41f02a1dea2c', '{}', '2025-03-26 17:43:26', '2025-03-26 17:43:26');
INSERT INTO public.connections VALUES ('9e86a333-5040-477e-8337-de8a108372bf', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-4b1e-4d39-b5bf-11d43d714edf', 'education', '9e86a333-4d35-424a-aff3-8465e7faa147', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-5c2f-4333-b925-3c5d8c45999d', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-57ed-485f-ae53-313375d1fb19', 'education', '9e86a333-59a9-46e7-ae03-5f42439df1e8', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-66c5-4f87-8d8e-97c5ea3efa1b', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-62e7-4d95-bc1f-6634f789e77b', 'education', '9e86a333-6465-4766-930f-bb021972f517', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-708d-4791-b05f-31ba59f600ef', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-6d17-467a-9695-f7489f5714bc', 'employment', '9e86a333-6e77-4fde-bfa4-990a384b5720', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-79b3-4c6e-9172-c8923609a5eb', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-765b-47ec-95e6-772fb1ca16c8', 'employment', '9e86a333-77a5-493b-9372-bb272ff00c92', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-8280-4de1-823a-00199a3c5774', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-7f4c-4e25-be49-47f78b329bd4', 'employment', '9e86a333-8094-40ba-acfb-6ced9decfd8d', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-8ad4-4461-85bd-6e389803ca0e', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-87ef-4f93-848d-7d726c46194f', 'employment', '9e86a333-8918-4cec-abaf-b19ad20e828b', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-92a2-4d56-83de-36cbb994b50b', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-8fdb-4af3-99af-3b8ca67dfa46', 'employment', '9e86a333-90e4-49f4-9aa8-95c5a9567798', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-9a7d-41ba-aa46-f47b51a2c575', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-9777-4c9a-a1f0-235df757dca9', 'residence', '9e86a333-98b1-480a-b38e-239696af3ceb', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-a244-419b-9bcf-8accc60b6d93', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-9f6b-41c9-9a7b-8e408d200017', 'residence', '9e86a333-a0ab-4304-b86d-7efcecb72b8b', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-aa35-4a43-9224-04a092e55b40', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-a768-4276-90e6-6a0f20a59b20', 'residence', '9e86a333-a88c-4339-88b3-18424a7c53bc', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-b1e7-4c80-ac5a-6a32e64e00e4', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a333-af2c-414f-9dcd-54de81b91258', 'residence', '9e86a333-b04b-454a-8685-7b7c809ed987', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-b917-4b00-bfdc-b70c4cbd46c4', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a325-6add-4436-8489-13870be94e31', 'relationship', '9e86a333-b76c-47e4-bab9-4f855b4714b4', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:28');
INSERT INTO public.connections VALUES ('9e86a333-4168-40db-99d7-c37c6339518f', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a302-fd8c-4496-9453-5dad183b6c66', 'family', '9e86a333-3e0b-4018-b2da-ac4c571dc068', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:29');
INSERT INTO public.connections VALUES ('9e86a335-add2-4277-bab3-e7873044cd54', '9e86a335-a639-4d6b-9600-63a71aa0142b', '9e86a335-a8e4-4b37-8d8a-244c216d0ea4', 'employment', '9e86a335-aaa0-4591-9788-e57ab9afa5b2', '{}', '2025-03-26 17:43:30', '2025-03-26 17:43:30');
INSERT INTO public.connections VALUES ('9e86a336-a490-4446-8d22-91549fc3b4b2', '9e86a336-9642-4ba4-977e-ed5ac6849642', '9e86a336-99e8-426a-8c6c-2bd9754f6feb', 'education', '9e86a336-9dde-4e76-a05d-53ccd732975a', '{}', '2025-03-26 17:43:30', '2025-03-26 17:43:30');
INSERT INTO public.connections VALUES ('9e86a336-c23e-495d-a195-ef97b03f7d6b', '9e86a336-9642-4ba4-977e-ed5ac6849642', '9e86a336-b9a7-4ea4-9401-2a0b38dffa6c', 'education', '9e86a336-bd2d-4721-9c56-732f3d1f7375', '{}', '2025-03-26 17:43:30', '2025-03-26 17:43:30');
INSERT INTO public.connections VALUES ('9e86a336-d541-49e6-bee2-0fb02421a8c3', '9e86a336-9642-4ba4-977e-ed5ac6849642', '9e86a317-e5b8-4208-92fb-dcb53456ccc6', 'employment', '9e86a336-d198-47b9-9d4f-ac81e6474a75', '{}', '2025-03-26 17:43:30', '2025-03-26 17:43:30');
INSERT INTO public.connections VALUES ('9e86a337-caeb-48c4-a4d9-9ba6d528795f', '9e86a337-bd41-4836-8e1d-05e4703f0747', '9e86a337-c0cf-481f-b1a2-45eb0f233f0e', 'education', '9e86a337-c4ab-4791-a84e-34bcaf2c98b4', '{}', '2025-03-26 17:43:31', '2025-03-26 17:43:31');
INSERT INTO public.connections VALUES ('9e86a337-e8d9-4d72-b84b-5f0d8b464f53', '9e86a337-bd41-4836-8e1d-05e4703f0747', '9e86a337-e080-43d4-aad6-bdc59990ccbd', 'employment', '9e86a337-e448-4e61-a9de-1826fc8a5655', '{}', '2025-03-26 17:43:31', '2025-03-26 17:43:31');
INSERT INTO public.connections VALUES ('9e86a337-fa5a-4e34-bd43-ad3df6d0e8d3', '9e86a337-bd41-4836-8e1d-05e4703f0747', '9e86a337-f5d7-4ba0-87c3-92aa4522c429', 'residence', '9e86a337-f7d5-47a7-9ccb-c80421997434', '{}', '2025-03-26 17:43:31', '2025-03-26 17:43:31');
INSERT INTO public.connections VALUES ('9e86a338-050e-4cc1-9c59-ff2d936820c5', '9e86a337-bd41-4836-8e1d-05e4703f0747', '9e86a338-019e-4efd-a914-91cb4fbe243c', 'residence', '9e86a338-0303-4dd4-b457-bbd427d57987', '{}', '2025-03-26 17:43:31', '2025-03-26 17:43:31');
INSERT INTO public.connections VALUES ('9e86a33a-a01f-428d-9b81-c11945a2c2ee', '9e86a33a-909c-4aac-b0cd-3e1962de266d', '9e86a33a-94ba-4cc3-97f2-099593ff553c', 'education', '9e86a33a-9939-4b43-bd58-5cb5d1a69c57', '{}', '2025-03-26 17:43:33', '2025-03-26 17:43:33');
INSERT INTO public.connections VALUES ('9e86a33a-bceb-475d-857a-aa0b18d020a2', '9e86a33a-909c-4aac-b0cd-3e1962de266d', '9e86a33a-b483-4969-aaf7-cc50d2ad5933', 'education', '9e86a33a-b848-4c90-8fd5-0692b24a1656', '{}', '2025-03-26 17:43:33', '2025-03-26 17:43:33');
INSERT INTO public.connections VALUES ('9e86a33a-cfb2-4e0b-9540-317d43a834e4', '9e86a33a-909c-4aac-b0cd-3e1962de266d', '9e86a33a-ca11-4001-aded-62019f82a541', 'education', '9e86a33a-cc74-4049-9272-589966cde076', '{}', '2025-03-26 17:43:33', '2025-03-26 17:43:33');
INSERT INTO public.connections VALUES ('9e86a33a-dc9a-4bd9-bdcc-d2ea2fd16fa8', '9e86a33a-909c-4aac-b0cd-3e1962de266d', '9e86a329-67af-47f8-a9b8-6783ee61c77d', 'employment', '9e86a33a-da1c-45db-998a-fd7e15756f65', '{}', '2025-03-26 17:43:33', '2025-03-26 17:43:33');
INSERT INTO public.connections VALUES ('9e86a33a-e89f-4003-ba58-4366cbce5ffa', '9e86a33a-909c-4aac-b0cd-3e1962de266d', '9e86a33a-e491-4307-8b03-cfa8f6151d80', 'residence', '9e86a33a-e646-4d0d-a8a2-0e6e2e65dbdd', '{}', '2025-03-26 17:43:33', '2025-03-26 17:43:33');
INSERT INTO public.connections VALUES ('9e86a33a-f3fb-46c1-90f6-59fbafca7ebd', '9e86a33a-909c-4aac-b0cd-3e1962de266d', '9e86a33a-f02a-4d6f-b39b-7aff2129c062', 'residence', '9e86a33a-f1b4-410a-86e9-18190c6e2375', '{}', '2025-03-26 17:43:33', '2025-03-26 17:43:33');
INSERT INTO public.connections VALUES ('9e86a33b-e35d-4bdc-bdda-2a05f081238b', '9e86a33b-d86f-4921-ba5a-b86e1b0310ea', '9e86a33b-d590-4ff2-a44d-9281e5d0f93a', 'membership', '9e86a33b-dc4c-48c0-951d-2ae99bef3c9b', '{}', '2025-03-26 17:43:34', '2025-03-26 17:43:34');
INSERT INTO public.connections VALUES ('9e86a333-2e39-4b2e-acc0-c311507f3528', '9e86a325-4f73-4533-97f5-13d827a91f81', '9e86a2fe-334f-46dc-acfb-79ac5489643d', 'family', '9e86a333-2967-4bf9-b630-26cde2800192', '{}', '2025-03-26 17:43:28', '2025-03-26 17:43:51');
INSERT INTO public.connections VALUES ('9e86a33c-0014-4d22-88bd-816b25dda2cf', '9e86a33b-f88f-4cd3-800c-be2fabaeebb3', '9e86a33b-d590-4ff2-a44d-9281e5d0f93a', 'membership', '9e86a33b-fc51-4e1a-bd57-602833a887fe', '{}', '2025-03-26 17:43:34', '2025-03-26 17:43:34');
INSERT INTO public.connections VALUES ('9e86a33c-1165-4834-a752-d0ab521309da', '9e86a33c-0c07-4776-a92b-76582e48231d', '9e86a33b-d590-4ff2-a44d-9281e5d0f93a', 'membership', '9e86a33c-0e38-4129-8b3c-7d110c0ff6e4', '{}', '2025-03-26 17:43:34', '2025-03-26 17:43:34');
INSERT INTO public.connections VALUES ('9e86a33c-f7ae-4c27-8acf-a30ebd88f8cf', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a33c-f185-4103-a5b1-6ab70944dd54', 'education', '9e86a33c-f3fd-481c-b9ba-fc124a07a140', '{}', '2025-03-26 17:43:34', '2025-03-26 17:43:34');
INSERT INTO public.connections VALUES ('9e86a33d-0b91-4df6-8ac7-5d139b7d564f', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a33d-0856-44e8-a4bb-767801c18b37', 'education', '9e86a33d-09b4-4cf9-9b50-8dd42addcd61', '{}', '2025-03-26 17:43:34', '2025-03-26 17:43:34');
INSERT INTO public.connections VALUES ('9e86a33d-1582-4b5d-814c-c31dde29e271', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a316-95b4-4333-9ab9-5fbbaaf58103', 'education', '9e86a33d-138d-4844-a7b7-ce0d228bdaba', '{}', '2025-03-26 17:43:34', '2025-03-26 17:43:34');
INSERT INTO public.connections VALUES ('9e86a33d-216c-4e5b-a3f7-050ca7a74c9a', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a316-a3be-49ca-bc73-2144e7a865e4', 'employment', '9e86a33d-1f49-47ca-9779-73e06c6221aa', '{}', '2025-03-26 17:43:34', '2025-03-26 17:43:34');
INSERT INTO public.connections VALUES ('9e86a33d-2e3e-41a7-b129-f0ac13b47ed7', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a33d-2a76-4abe-b26c-70213b0ed223', 'employment', '9e86a33d-2c0b-4b32-a8ce-9d3adf1bf8b2', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a33d-3985-4cda-81a2-864e90eda5bc', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a329-67af-47f8-a9b8-6783ee61c77d', 'employment', '9e86a33d-375a-4b42-b7de-9becfeaa39fe', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a33d-4412-478d-9b22-80f5f1d7a3f2', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a316-b8ee-46be-bda8-126d9140c341', 'residence', '9e86a33d-41d9-439b-8688-331c1f7710ed', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a33d-4e23-415d-a0b9-7a4c1682e022', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a333-af2c-414f-9dcd-54de81b91258', 'residence', '9e86a33d-4c25-4948-a6d7-19ef82274ad2', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a33d-57ba-4007-b1ac-1d61f37ade79', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a33d-547d-427e-8da4-1f5c53ff6310', 'residence', '9e86a33d-55b9-457a-8c4c-1ba4bacda734', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a33d-600a-4740-9623-9e7d4c0a55df', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a30b-845a-4797-9d5c-98f81bcd02bb', 'residence', '9e86a33d-5e75-4731-bea2-493665c2d9b7', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a33d-6902-4ec2-9611-0499b2ff72a3', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a33d-65d4-4bd2-9aa3-1be108c4f5f9', 'relationship', '9e86a33d-6720-4891-bb85-f24e67222e2d', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a33d-722e-482d-962e-aa8aae884fc4', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a33d-6f28-45f8-b3dc-cdcd245db913', 'relationship', '9e86a33d-705c-498b-8b4a-b3ea45433d73', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a33d-7b79-4e09-8482-99c4209795f1', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a33d-7867-448a-9b36-9766079d19e2', 'relationship', '9e86a33d-7991-42a9-8d1f-2a1c64cb70fa', '{}', '2025-03-26 17:43:35', '2025-03-26 17:43:35');
INSERT INTO public.connections VALUES ('9e86a331-4c2f-4a17-afe9-92c5175e37c3', '9e86a331-4092-4f87-bd11-a58d3722d9fd', '9e86a309-44ef-4098-9452-983f2809bebe', 'family', '9e86a331-45aa-45b2-ad2e-cd0002116ffa', '{}', '2025-03-26 17:43:27', '2025-03-26 17:43:36');
INSERT INTO public.connections VALUES ('9e86a340-8609-4e9b-a0a7-8821d1065bc7', '9e86a30a-3b45-4452-b5bf-9a6aa91b3ae9', '9e86a340-7b54-4453-b74b-e33892278a5d', 'membership', '9e86a340-8075-452d-bc4e-797df51ab664', '{}', '2025-03-26 17:43:37', '2025-03-26 17:43:37');
INSERT INTO public.connections VALUES ('9e86a340-a2fa-4c6c-b141-823bd2b54a90', '9e86a340-9b45-46e9-9935-720fb5faf7bf', '9e86a340-7b54-4453-b74b-e33892278a5d', 'membership', '9e86a340-9f45-450b-a991-425d87be039c', '{}', '2025-03-26 17:43:37', '2025-03-26 17:43:37');
INSERT INTO public.connections VALUES ('9e86a340-b4af-49a7-96ed-cac679672681', '9e86a340-afbe-4855-9016-355aeedd8624', '9e86a340-7b54-4453-b74b-e33892278a5d', 'membership', '9e86a340-b1e5-4c13-ac7a-9b1b7e05865d', '{}', '2025-03-26 17:43:37', '2025-03-26 17:43:37');
INSERT INTO public.connections VALUES ('9e86a341-a0e3-4035-af91-3543a06479e4', '9e86a337-bd41-4836-8e1d-05e4703f0747', '9e86a341-97ea-4174-a7d3-7c44700210a3', 'membership', '9e86a341-9c2b-4760-9ce4-41d9ee102a5e', '{}', '2025-03-26 17:43:37', '2025-03-26 17:43:37');
INSERT INTO public.connections VALUES ('9e86a341-bf37-480f-9756-e5e8deaa657c', '9e86a341-b6fa-4d56-a5ae-83a5442a57cc', '9e86a341-97ea-4174-a7d3-7c44700210a3', 'membership', '9e86a341-bb52-4402-90de-2a7dc32976ba', '{}', '2025-03-26 17:43:38', '2025-03-26 17:43:38');
INSERT INTO public.connections VALUES ('9e86a341-d13f-4979-b1b9-402cc2e87040', '9e86a341-cbb4-4ebc-8cf8-8b5dbef63df7', '9e86a341-97ea-4174-a7d3-7c44700210a3', 'membership', '9e86a341-ce1e-4bb3-8ecc-ea1971277562', '{}', '2025-03-26 17:43:38', '2025-03-26 17:43:38');
INSERT INTO public.connections VALUES ('9e86a341-dea4-4f73-8ae6-ac1b318eeebd', '9e86a341-da69-40b6-8120-a6ed82c08a51', '9e86a341-97ea-4174-a7d3-7c44700210a3', 'membership', '9e86a341-dc16-4752-a1f5-6ed9587d2257', '{}', '2025-03-26 17:43:38', '2025-03-26 17:43:38');
INSERT INTO public.connections VALUES ('9e86a342-cb8a-4060-891f-919475b0ee98', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', '9e86a342-bd97-447c-8f37-14123ed29b85', 'membership', '9e86a342-c52c-4e27-9138-1fa8ecc3b8c7', '{}', '2025-03-26 17:43:38', '2025-03-26 17:43:38');
INSERT INTO public.connections VALUES ('9e86a342-e90c-4e62-b624-6fbe007c4928', '9e86a342-e04f-4634-8836-011494e95d63', '9e86a342-bd97-447c-8f37-14123ed29b85', 'membership', '9e86a342-e452-4416-93eb-d2479d25315f', '{}', '2025-03-26 17:43:38', '2025-03-26 17:43:38');
INSERT INTO public.connections VALUES ('9e86a342-fb91-4c7b-a5b6-3f0eac545aee', '9e86a342-f619-4820-953a-9016284fa360', '9e86a342-bd97-447c-8f37-14123ed29b85', 'membership', '9e86a342-f86a-4476-a7df-6023d52b5734', '{}', '2025-03-26 17:43:38', '2025-03-26 17:43:38');
INSERT INTO public.connections VALUES ('9e86a343-08f7-441d-bca7-53a4591ba91f', '9e86a343-04cc-4c8c-ac54-721e056ac804', '9e86a342-bd97-447c-8f37-14123ed29b85', 'membership', '9e86a343-068d-474d-a474-a17258e2c6ec', '{}', '2025-03-26 17:43:38', '2025-03-26 17:43:38');
INSERT INTO public.connections VALUES ('9e86a343-149d-48f5-a42b-6f4c1770be3a', '9e86a343-10d2-46a0-be81-840c5cdeffcf', '9e86a342-bd97-447c-8f37-14123ed29b85', 'membership', '9e86a343-1278-402a-a5de-1c4d236a708e', '{}', '2025-03-26 17:43:38', '2025-03-26 17:43:38');
INSERT INTO public.connections VALUES ('9e86a344-03a5-459d-b23d-f741a0b5bc55', '9e86a343-f9ef-452d-b0f2-f7331573e30e', '9e86a343-f659-442d-aad0-1dcdfcfefbff', 'family', '9e86a343-fd92-4635-93b8-7a7a5b86e69b', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-1e5d-4a2a-9b61-6bb6bf07b023', '9e86a344-1782-46c7-bdfa-1424b0cd8988', '9e86a343-f659-442d-aad0-1dcdfcfefbff', 'family', '9e86a344-1ac0-46da-8336-d9977c30ea74', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-2e3a-4018-8284-c4522c505de4', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a344-29b5-4b67-9571-3c62a9eb1773', 'family', '9e86a344-2b90-4468-aaae-362b2a4ba19c', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-3a6a-407d-b32c-b876cb82f5ec', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a344-36cb-4fbc-9a4c-0d119ea8844c', 'education', '9e86a344-3834-4289-94ea-9db7197977f4', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-457f-43ce-abc7-86edf7f80e50', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a344-4202-4ab4-a5c5-53cef93ff477', 'education', '9e86a344-4374-485e-8f5d-29590be87336', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-4ef3-493d-af4e-f9f4a0025108', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a32d-8a67-4eb0-8e3a-ab0d8c5896ff', 'education', '9e86a344-4cd9-426f-928b-983922c9af5f', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-5901-4375-a235-6e10205bfb53', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a344-55be-4f82-8071-6bcbd44d95cf', 'residence', '9e86a344-5703-4029-b465-35f1a4d4d3e9', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-614b-443c-a5a7-ef8a0799862d', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a333-9f6b-41c9-9a7b-8e408d200017', 'residence', '9e86a344-5f97-46ed-b8b6-5577b76db3fd', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-69d2-4159-98fd-5b9a0f3ae07d', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a344-66a7-4ee6-8e2d-d20c3874b736', 'relationship', '9e86a344-67f2-4bc0-ac1b-01ec8dba6250', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-721e-4c07-b109-d3a05346d9f0', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a344-6f3f-4e60-8b90-c09d3238c42e', 'relationship', '9e86a344-7084-4558-ab5e-20b13fb8ac52', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a344-7a7b-4c6e-a4fa-edfe33e04a72', '9e86a343-f659-442d-aad0-1dcdfcfefbff', '9e86a344-77a2-4c9d-b8dd-3684f3d547a7', 'relationship', '9e86a344-78ea-4e53-b700-6643905af7c4', '{}', '2025-03-26 17:43:39', '2025-03-26 17:43:39');
INSERT INTO public.connections VALUES ('9e86a345-8b20-4a57-8491-f1674dcf190d', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-84e2-4c62-9e2e-4a307a61cafe', 'education', '9e86a345-87b0-4066-8673-3e140c97f8c6', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-9be3-4f7d-8a5e-33c0b8b6839f', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-97a4-4946-a66f-533baebff773', 'education', '9e86a345-9940-4dd2-aed8-c5efa80323e2', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-a778-463e-964a-edc8c8ec7b66', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a336-99e8-426a-8c6c-2bd9754f6feb', 'education', '9e86a345-a4d5-4ffc-9329-244220433425', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-b3de-4c12-a428-34760cea275d', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-b01c-4fde-9daf-972b8adbcfb4', 'employment', '9e86a345-b1b2-4265-acea-b31f47a24c19', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-be0c-431d-a066-43d39ff02af8', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a336-99e8-426a-8c6c-2bd9754f6feb', 'employment', '9e86a345-bc02-4cfe-87fb-c5ea99199eec', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-c709-459c-9141-e2c44f157b32', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a310-8ae4-48a7-94cb-4cc383af2b83', 'employment', '9e86a345-c54f-4bd6-ba28-56652ed7cb11', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-d013-4491-86ba-6deafaa3159d', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-cd19-4e48-9bef-5747354107c5', 'employment', '9e86a345-ce40-4b82-9119-b70b5d1d1984', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-da47-4e75-baa0-71d92aa98391', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-d77d-4259-8aff-471d7491d604', 'residence', '9e86a345-d8b7-4008-8782-b142349b4074', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-e2e1-4618-9ebc-eaa57ed00349', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-dffb-407e-960d-14dff1553a86', 'residence', '9e86a345-e122-43de-9ac5-c43fca94b20e', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-eb2e-41fc-865d-bd9c3ce8d8f6', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-d77d-4259-8aff-471d7491d604', 'residence', '9e86a345-e96c-4692-9a84-63469809919b', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-f3cf-4b91-b172-dd624559ba86', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-f0ff-484c-9155-5d3b1d981e3c', 'relationship', '9e86a345-f233-462d-a7e1-3f169f345de3', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a345-fc92-45f9-93c4-dc8cc90dd8c9', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a345-f990-4ef6-b4fd-4070cea58f73', 'relationship', '9e86a345-fab6-42ea-b198-6a7b14bf07fc', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a346-04a6-4219-9630-853760a754e4', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', '9e86a2fd-2311-40d2-ac40-e6761c9b4504', 'relationship', '9e86a346-02e3-4993-a2e4-fa87e1a489a5', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:40');
INSERT INTO public.connections VALUES ('9e86a346-f14a-4478-95af-9e3d5edfaf43', '9e86a346-e7ec-44d0-b584-22a9ab0956fc', '9e86a346-e44e-4bc2-b535-a4caaaed9499', 'family', '9e86a346-eb25-4120-9464-389d0d91668d', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-0e07-4b8c-87e0-9b231977b41d', '9e86a347-06bd-4754-aa71-17e0b915349d', '9e86a346-e44e-4bc2-b535-a4caaaed9499', 'family', '9e86a347-09df-41da-9759-e001aac8c74e', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-1f3e-471a-8841-6f960f165240', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a347-1ae3-428d-9f17-2c79abbd8c27', 'family', '9e86a347-1ca3-44ab-a3ed-6ccd2ff5a185', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-2bed-4dec-82f4-b92346ce77ea', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a347-2838-41b7-a468-c9778a2a6b42', 'family', '9e86a347-29b4-4114-a576-dc035b4c171e', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-36bb-4c1a-a566-34027b4fb48d', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a347-3364-4804-a524-3462b0a5d986', 'family', '9e86a347-34a1-4bc7-a8de-f47fa746f468', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-4014-4d7a-bec0-ba6bc15954da', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a347-3d3e-4783-8034-59ba1512af07', 'family', '9e86a347-3e5f-4e49-a250-8d00d37d19a5', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-4857-4843-a219-dfe155e0078f', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a316-a3be-49ca-bc73-2144e7a865e4', 'employment', '9e86a347-469a-40bc-8498-5525d2aeca7b', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-5153-4c6f-ad35-b0fbc9b7d2b9', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a347-4e72-4ba2-a540-a8cc7f50a2fe', 'employment', '9e86a347-4f9f-4620-b35b-499dc136d73d', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-598c-4be6-9f93-0dc7ce5cfe14', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a316-b8ee-46be-bda8-126d9140c341', 'residence', '9e86a347-57e7-4762-84e5-55d2656d581d', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-619c-40df-a10c-d0c72d3dcb33', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a316-c302-4d1d-8a67-ac0a992fe4de', 'residence', '9e86a347-6010-4a20-ba91-7296a5861ac8', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-6aa8-4faa-b5aa-ef15456734c4', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a347-6793-4189-bab1-a80b0ce68014', 'relationship', '9e86a347-68ea-499b-aa8d-e521d5678d50', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a347-7395-4fda-aa2c-7cb4d990782b', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a347-70c3-4351-b7e3-72e19fa4baaa', 'relationship', '9e86a347-71f1-4ea0-b2d7-15357e862b69', '{}', '2025-03-26 17:43:41', '2025-03-26 17:43:41');
INSERT INTO public.connections VALUES ('9e86a345-66ab-44b0-b5dd-3af7f7b88866', '9e86a318-f3a8-4eab-a2d9-9d99d3b5b0fd', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', 'family', '9e86a345-6103-4701-9bc4-e8f5a2ab5f13', '{}', '2025-03-26 17:43:40', '2025-03-26 17:43:44');
INSERT INTO public.connections VALUES ('9e86a34a-7cc6-4ad3-b166-2f5cfc103bdb', '9e86a315-6045-4320-83b3-b40f42e09013', '9e86a301-f1bb-45dc-a729-e7ce5c688c3e', 'family', '9e86a34a-78d5-491f-8a09-cdfb2967b23f', '{}', '2025-03-26 17:43:43', '2025-03-26 17:43:45');
INSERT INTO public.connections VALUES ('9e86a34b-9f48-4453-9214-f349cc071907', '9e86a318-f3a8-4eab-a2d9-9d99d3b5b0fd', '9e86a315-6045-4320-83b3-b40f42e09013', 'family', '9e86a34b-9d00-4c7a-ae36-d2645b31c228', '{}', '2025-03-26 17:43:44', '2025-03-26 17:43:45');
INSERT INTO public.connections VALUES ('9e86a348-74c4-44c3-abae-f401962890ee', '9e86a31d-7aad-45b8-b6cb-c48b6c48e944', '9e86a31f-6eb7-4799-90c4-9dc1bad51cfd', 'family', '9e86a348-7043-4199-8946-cd1f6fbadab0', '{}', '2025-03-26 17:43:42', '2025-03-26 17:43:45');
INSERT INTO public.connections VALUES ('9e86a34e-a530-4417-acda-673308b780a9', '9e86a34e-9b28-43fa-8ddb-363dff4392b0', '9e86a34e-9ddf-47c9-a82a-62f61f99785f', 'employment', '9e86a34e-a0a4-4f14-b194-8def0991f714', '{}', '2025-03-26 17:43:46', '2025-03-26 17:43:46');
INSERT INTO public.connections VALUES ('9e86a34e-bac0-454a-859e-0104fa1c98fa', '9e86a34e-9b28-43fa-8ddb-363dff4392b0', '9e86a311-dfbc-4140-b974-f061c264f17b', 'employment', '9e86a34e-b7c0-49f9-8f29-3ce4d7e5b84f', '{}', '2025-03-26 17:43:46', '2025-03-26 17:43:46');
INSERT INTO public.connections VALUES ('9e86a34e-c9c4-491e-8439-4bedb974b6e2', '9e86a34e-9b28-43fa-8ddb-363dff4392b0', '9e86a311-fc84-487c-9bb5-bf1e220e3634', 'residence', '9e86a34e-c732-411e-9f74-866440efb795', '{}', '2025-03-26 17:43:46', '2025-03-26 17:43:46');
INSERT INTO public.connections VALUES ('9e86a34f-b936-48df-a598-a32268154eb7', '9e86a34f-adee-4685-84a7-f0074e864af9', '9e86a34f-aae4-4ba0-9c48-ac874058e157', 'family', '9e86a34f-b1ea-4e1c-a36e-8e11db0a71b8', '{}', '2025-03-26 17:43:47', '2025-03-26 17:43:47');
INSERT INTO public.connections VALUES ('9e86a34f-d78e-460d-994a-f0444089c435', '9e86a34f-d06d-470c-a05b-65f3e718637e', '9e86a34f-aae4-4ba0-9c48-ac874058e157', 'family', '9e86a34f-d3c4-4a3f-87e3-bae101d58b8d', '{}', '2025-03-26 17:43:47', '2025-03-26 17:43:47');
INSERT INTO public.connections VALUES ('9e86a34f-e851-480d-90b6-cd65b5a30048', '9e86a34f-aae4-4ba0-9c48-ac874058e157', '9e86a34f-e40a-48b0-8fa7-0b479b413c4c', 'employment', '9e86a34f-e5d0-4022-9fdd-8824bd485dfa', '{}', '2025-03-26 17:43:47', '2025-03-26 17:43:47');
INSERT INTO public.connections VALUES ('9e86a34f-f4ea-4340-90e6-cde77b3c1eec', '9e86a34f-aae4-4ba0-9c48-ac874058e157', '9e86a34f-f185-4037-b613-9f3fe9d6f942', 'employment', '9e86a34f-f2cd-43b7-9335-f36a6131b416', '{}', '2025-03-26 17:43:47', '2025-03-26 17:43:47');
INSERT INTO public.connections VALUES ('9e86a350-0028-4153-a0ec-af8a3d890995', '9e86a34f-aae4-4ba0-9c48-ac874058e157', '9e86a34f-fcbd-40da-8b94-5f47a8d3c532', 'residence', '9e86a34f-fe4c-43af-88dd-24c5f8863c6b', '{}', '2025-03-26 17:43:47', '2025-03-26 17:43:47');
INSERT INTO public.connections VALUES ('9e86a350-0a63-4494-b495-1e53f45edfe8', '9e86a34f-aae4-4ba0-9c48-ac874058e157', '9e86a350-0728-46d5-abeb-55fa96fb162e', 'residence', '9e86a350-0886-4f77-8128-ed527a420a8b', '{}', '2025-03-26 17:43:47', '2025-03-26 17:43:47');
INSERT INTO public.connections VALUES ('9e86a350-139c-47a5-8cd6-df43ec84fa8d', '9e86a34f-aae4-4ba0-9c48-ac874058e157', '9e86a350-10c7-496a-b312-9d9580a3f278', 'residence', '9e86a350-120a-4dc1-b474-222bad4b9004', '{}', '2025-03-26 17:43:47', '2025-03-26 17:43:47');
INSERT INTO public.connections VALUES ('9e86a350-1cdb-4edf-85b0-a23a25c46c5b', '9e86a34f-aae4-4ba0-9c48-ac874058e157', '9e86a350-19cb-451d-a7a4-adafbe4760ef', 'relationship', '9e86a350-1af2-40cb-ade9-c0ab93955e19', '{}', '2025-03-26 17:43:47', '2025-03-26 17:43:47');
INSERT INTO public.connections VALUES ('9e86a351-075f-43d2-b332-e988c44dc5fe', '9e86a326-580e-4718-bf84-2342e6dda3b0', '9e86a350-fb64-4dcf-8fb0-33fe4f2987de', 'membership', '9e86a351-002d-48a4-abd1-eac80fdf271d', '{}', '2025-03-26 17:43:48', '2025-03-26 17:43:48');
INSERT INTO public.connections VALUES ('9e86a351-231d-4a0b-b6c8-69d5619c5d02', '9e86a33c-efc2-4db9-9106-610021dd8193', '9e86a350-fb64-4dcf-8fb0-33fe4f2987de', 'membership', '9e86a351-1f6d-4f5d-8523-947cfcb444c8', '{}', '2025-03-26 17:43:48', '2025-03-26 17:43:48');
INSERT INTO public.connections VALUES ('9e86a351-3477-4aae-bd82-9f61ecf4ce01', '9e86a316-4e33-433d-8ffd-5779d0451f8a', '9e86a350-fb64-4dcf-8fb0-33fe4f2987de', 'membership', '9e86a351-31e3-4259-8947-172f9704a4ae', '{}', '2025-03-26 17:43:48', '2025-03-26 17:43:48');
INSERT INTO public.connections VALUES ('9e86a351-40c3-480f-bdbd-fa8c07cd3097', '9e86a346-e44e-4bc2-b535-a4caaaed9499', '9e86a350-fb64-4dcf-8fb0-33fe4f2987de', 'membership', '9e86a351-3e9e-4f4f-9540-594651c712b7', '{}', '2025-03-26 17:43:48', '2025-03-26 17:43:48');
INSERT INTO public.connections VALUES ('9e86a352-1f4c-486f-90b5-04a7f32a5a6e', '9e86a321-7306-4937-b66d-4b45f6f2ad0d', '9e86a352-195d-4023-8309-9f2e345981dd', 'membership', '9e86a352-1b8a-4f70-ac68-440679a8c352', '{}', '2025-03-26 17:43:48', '2025-03-26 17:43:48');
INSERT INTO public.connections VALUES ('9e86a352-3110-4e2e-90b9-576fbbca23ee', '9e86a352-2e03-4ff3-8ee7-96d698bdd7ec', '9e86a352-195d-4023-8309-9f2e345981dd', 'membership', '9e86a352-2f49-4849-8ea8-1a7754a2c57a', '{}', '2025-03-26 17:43:48', '2025-03-26 17:43:48');
INSERT INTO public.connections VALUES ('9e86a352-3bd8-4f5f-9526-f689f6d61fdb', '9e86a352-388b-4e1d-acd7-622897bbb606', '9e86a352-195d-4023-8309-9f2e345981dd', 'membership', '9e86a352-3a00-463d-b869-091fb3915ec2', '{}', '2025-03-26 17:43:48', '2025-03-26 17:43:48');
INSERT INTO public.connections VALUES ('9e86a353-2933-4614-8675-147713091ee9', '9e86a34e-9b28-43fa-8ddb-363dff4392b0', '9e86a353-1d9a-4569-9eb1-8b3f2a06a5b4', 'membership', '9e86a353-22ca-40c2-9a61-3be860aae1a2', '{}', '2025-03-26 17:43:49', '2025-03-26 17:43:49');
INSERT INTO public.connections VALUES ('9e86a353-48aa-46f2-a416-4db0cc35b17a', '9e86a353-40fe-4cbe-af7e-eb567eeca2e4', '9e86a353-1d9a-4569-9eb1-8b3f2a06a5b4', 'membership', '9e86a353-44d3-494b-b3ee-7bf9f47a91c1', '{}', '2025-03-26 17:43:49', '2025-03-26 17:43:49');
INSERT INTO public.connections VALUES ('9e86a353-5b15-40e1-a062-3e1afb76938a', '9e86a353-5673-4a01-ad32-25ff25b8fd30', '9e86a353-1d9a-4569-9eb1-8b3f2a06a5b4', 'membership', '9e86a353-5892-403a-a008-9755548fc922', '{}', '2025-03-26 17:43:49', '2025-03-26 17:43:49');
INSERT INTO public.connections VALUES ('9e86a353-68ff-4e53-9b2e-e6b1e10452f6', '9e86a353-6563-4ce0-9458-8ab9476941d6', '9e86a353-1d9a-4569-9eb1-8b3f2a06a5b4', 'membership', '9e86a353-66db-44b0-8d01-0e1fe85e1e5c', '{}', '2025-03-26 17:43:49', '2025-03-26 17:43:49');
INSERT INTO public.connections VALUES ('9e86a353-7548-4e07-b411-b5ce4ca4d447', '9e86a353-71d6-4c45-845f-5672fb3c30bb', '9e86a353-1d9a-4569-9eb1-8b3f2a06a5b4', 'membership', '9e86a353-7349-4b88-9c79-f399a85c772a', '{}', '2025-03-26 17:43:49', '2025-03-26 17:43:49');
INSERT INTO public.connections VALUES ('9e86a353-8061-4cd3-8a4f-1751b2b8244d', '9e86a353-7d1a-4a80-a2e8-d4d22d376123', '9e86a353-1d9a-4569-9eb1-8b3f2a06a5b4', 'membership', '9e86a353-7e96-4ea6-a153-cc0a8532c187', '{}', '2025-03-26 17:43:49', '2025-03-26 17:43:49');
INSERT INTO public.connections VALUES ('9e86a355-45ec-4213-afec-9d1200ab543f', '9e86a355-3c54-4ab2-a5a1-84b868ee969e', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', 'family', '9e86a355-4076-4dab-9d18-6745deb521ca', '{}', '2025-03-26 17:43:50', '2025-03-26 17:43:50');
INSERT INTO public.connections VALUES ('9e86a355-6624-4482-86a0-20801aa28099', '9e86a355-5ecc-4314-ac5c-aa6331bfde2c', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', 'family', '9e86a355-61f9-4f3f-ba01-ba4c722dbc7d', '{}', '2025-03-26 17:43:50', '2025-03-26 17:43:50');
INSERT INTO public.connections VALUES ('9e86a355-7876-40a1-a805-5911080e7577', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', '9e86a355-742a-433b-894e-60638cf953de', 'family', '9e86a355-75fe-4a26-98ea-996365152554', '{}', '2025-03-26 17:43:50', '2025-03-26 17:43:50');
INSERT INTO public.connections VALUES ('9e86a355-854c-413d-9dd4-18e8c8b890f1', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', '9e86a355-8213-4b9b-ac85-040c54c57e65', 'family', '9e86a355-8352-4a7c-967c-cc3a10d74006', '{}', '2025-03-26 17:43:50', '2025-03-26 17:43:50');
INSERT INTO public.connections VALUES ('9e86a355-90b4-4809-b084-acfb3e307d76', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', '9e86a355-8d79-4dc6-b853-5836f5bf5dd9', 'education', '9e86a355-8ecb-4c12-8f1e-ffcbfba82d9e', '{}', '2025-03-26 17:43:51', '2025-03-26 17:43:51');
INSERT INTO public.connections VALUES ('9e86a355-9afc-4ca1-9749-d0bff76222c6', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', '9e86a355-97f0-4940-b46b-25796de393b2', 'employment', '9e86a355-9931-47aa-a7a7-0a104b73e56f', '{}', '2025-03-26 17:43:51', '2025-03-26 17:43:51');
INSERT INTO public.connections VALUES ('9e86a355-a485-48d0-9105-e40e6456dbc2', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', '9e86a355-a1bf-45b6-987a-f62ba3aa549e', 'residence', '9e86a355-a2df-424e-a896-960f133cf713', '{}', '2025-03-26 17:43:51', '2025-03-26 17:43:51');
INSERT INTO public.connections VALUES ('9e86a355-ae78-44ff-a27a-85dbcbe52a8a', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', '9e86a355-ab54-46f2-b324-b0e4cf43cca3', 'residence', '9e86a355-ac90-40c4-af11-8f46c8437f1a', '{}', '2025-03-26 17:43:51', '2025-03-26 17:43:51');
INSERT INTO public.connections VALUES ('9e86a355-b828-4d65-883a-2a6196de4871', '9e86a342-c00d-45af-92c4-2f9b0e8082d5', '9e86a355-b53e-4e88-ba32-d3dd35688e81', 'relationship', '9e86a355-b687-4c13-a820-b6098ffd1bf2', '{}', '2025-03-26 17:43:51', '2025-03-26 17:43:51');
INSERT INTO public.connections VALUES ('9e86a357-b7be-4911-bff4-08c7c7e1f10f', '9e86a357-ae0e-4ace-9068-0ba4cd89bd1e', '9e86a357-ab12-429a-9758-4c44ccd788c1', 'family', '9e86a357-b18d-4335-bf8a-c409e945be26', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a357-d57e-48bf-a9ee-d73c0b7b5da8', '9e86a357-cf6a-452b-aaec-438ad32e87e7', '9e86a357-ab12-429a-9758-4c44ccd788c1', 'family', '9e86a357-d218-4a0b-bf76-09f5ef58dc83', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a357-e7e8-41e1-a584-8ba94f200e74', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a357-e3ed-473b-b5c0-dffea70c8de6', 'family', '9e86a357-e584-48bf-9406-c14f341075a7', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a357-f4da-4861-9fae-0c5292469194', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a357-f178-4e95-8f74-7c27f668c4cf', 'family', '9e86a357-f2e3-4497-8768-7f88f944f155', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-0043-45fc-b651-1a5312ddcdc6', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a357-fd39-4c7a-a8fa-67473dc3a4ff', 'family', '9e86a357-fe7b-42ee-a3cb-a7b8f1902b89', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-0974-490a-b9f6-c57968c9c313', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a357-cf6a-452b-aaec-438ad32e87e7', 'family', '9e86a358-07db-44ac-86c3-d9eb7e3dab1f', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-135c-4efc-986e-837518659db7', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a358-1064-4e5c-a8c6-f4de9ef15665', 'education', '9e86a358-11a1-427b-89dd-c180478cf9a7', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-1d08-46e4-9801-f4bfb77614cb', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a358-1a5c-4957-b4d8-29cb8cac2306', 'education', '9e86a358-1b63-4725-b150-730fcc892ce2', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-26e6-4d2b-8ded-742402454018', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a358-23ee-4b0a-8f85-ed5c464ad0aa', 'education', '9e86a358-2510-4989-8851-9cf7a03c1228', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-3055-4179-92bc-0a74b2d1e52a', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a32d-a9de-4cb5-8d11-155fd6c81484', 'employment', '9e86a358-2eac-4f8f-ac27-7357fa8f108e', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-39d4-4be3-b439-3ed91fea4102', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a32d-b2c9-45ab-a9e3-341272ad5c8f', 'employment', '9e86a358-37ec-4e68-b84b-a3fa18080eeb', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-453b-4139-b940-bcb4cfe6dfb3', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a358-426d-4497-b904-c241151b7b66', 'employment', '9e86a358-4382-47c7-8462-a60c4d804285', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-4f55-47dd-9bc1-8cf5efc1eef8', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a358-4c6b-4d5c-8504-9ae84e193846', 'residence', '9e86a358-4da7-46ed-b529-c1d8a8128130', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-594c-4324-8f6b-8d80618931cd', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a358-5653-43c6-9253-a72a055cc5d7', 'residence', '9e86a358-5783-42ac-bb6b-f2f21b5f0dba', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-62c0-4579-b7e9-ec47327dcef2', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a333-9f6b-41c9-9a7b-8e408d200017', 'residence', '9e86a358-610c-450a-b8d0-36d1a79dfec1', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-6cd6-405a-839f-60f71ccecd18', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a358-69ee-495c-999f-971884f7f49e', 'residence', '9e86a358-6b2b-44d5-abb1-55639a901868', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-7667-4329-b060-c94a4850dce5', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a333-af2c-414f-9dcd-54de81b91258', 'residence', '9e86a358-74ad-45f6-b340-da276587bc35', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a358-808b-4813-a1b5-c4ccda9c4fea', '9e86a357-ab12-429a-9758-4c44ccd788c1', '9e86a358-7d8f-4df1-a22c-f0920f490226', 'relationship', '9e86a358-7ecb-4218-bcae-b77fca7323d0', '{}', '2025-03-26 17:43:52', '2025-03-26 17:43:52');
INSERT INTO public.connections VALUES ('9e86a35c-c3e6-4dfe-89a7-60d2f60abf19', '9e86a35c-be2c-483f-af99-1ab1f4421035', '9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', 'family', '9e86a35c-c01b-4180-89b1-816a91b32a8b', '{}', '2025-03-26 17:43:55', '2025-03-26 17:43:55');
INSERT INTO public.connections VALUES ('9e86a35c-daba-442e-995e-4a27747d934a', '9e86a35c-d582-4e51-a8c2-f3556d4741da', '9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', 'family', '9e86a35c-d859-4333-8211-ba05392366fa', '{}', '2025-03-26 17:43:55', '2025-03-26 17:43:55');
INSERT INTO public.connections VALUES ('9e86a35c-e6fd-40c3-aae2-89a84a7c2291', '9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', '9e86a35c-e389-4eef-9bbd-b4e086cbcdf9', 'family', '9e86a35c-e4e5-456f-96a6-72140158761b', '{}', '2025-03-26 17:43:55', '2025-03-26 17:43:55');
INSERT INTO public.connections VALUES ('9e86a35c-f2c4-459c-ab2e-643b517bc888', '9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', '9e86a35c-ef73-4e6a-be14-69caf5f79afe', 'family', '9e86a35c-f0ba-42c6-97da-64fee95a81a9', '{}', '2025-03-26 17:43:55', '2025-03-26 17:43:55');
INSERT INTO public.connections VALUES ('9e86a35c-fe86-4ab8-948e-30ed7391464b', '9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', '9e86a35c-fb51-4000-9e42-6d832cff8aa3', 'education', '9e86a35c-fc7d-4336-8983-8623508ec8a9', '{}', '2025-03-26 17:43:55', '2025-03-26 17:43:55');
INSERT INTO public.connections VALUES ('9e86a35d-0a64-48de-9915-3d0a925bf51e', '9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', '9e86a35d-0739-42ed-81df-68a56ef03337', 'employment', '9e86a35d-086a-4fe7-9e3a-6359fb360e9a', '{}', '2025-03-26 17:43:55', '2025-03-26 17:43:55');
INSERT INTO public.connections VALUES ('9e86a35d-1b19-4908-b300-c3d4c2e2c164', '9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', '9e86a35d-17bf-45c5-916e-eec34cf079e8', 'residence', '9e86a35d-1906-472a-8dcd-22a2ed16fbc6', '{}', '2025-03-26 17:43:55', '2025-03-26 17:43:55');
INSERT INTO public.connections VALUES ('9e86a35d-262d-4d17-ad6b-e4b16c43a83e', '9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', '9e86a35d-231b-4a29-a94d-7b55d11503fd', 'residence', '9e86a35d-245e-4c18-99d8-64062079d614', '{}', '2025-03-26 17:43:55', '2025-03-26 17:43:55');
INSERT INTO public.connections VALUES ('9e86a35e-178a-468e-8bbc-8eba25c2711b', '9e86a35e-0c2d-4ae2-87d1-5ab19b37471f', '9e86a35e-084e-44c5-899f-64bb227f9014', 'family', '9e86a35e-0fa9-4c8d-a2d8-bf056f875a57', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-3805-4610-8348-116ce65229a1', '9e86a35e-2ff7-40d9-8210-2ea88e88898d', '9e86a35e-084e-44c5-899f-64bb227f9014', 'family', '9e86a35e-3351-4b88-b8c0-a1775ad5b56e', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-4a0e-4fdd-8c1a-45af2c11f6d1', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-45b3-47c4-9f9d-2afed3c048b7', 'family', '9e86a35e-4753-4be4-852d-569f83e205d9', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-57ba-47ef-87a7-2af1fcea6fed', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-542d-4b4f-bdeb-2940c9ab7827', 'family', '9e86a35e-5563-4967-9c4a-20d3152687ce', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-635c-408f-8d31-f9fb38c710dd', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-6019-4360-a6b9-fc90ac186820', 'family', '9e86a35e-614d-40c6-bc0f-5ba5e590ebb2', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-6e1d-4884-b0ca-19a3412271dd', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-6aca-46d8-b29e-cebe52f10832', 'family', '9e86a35e-6bd5-4258-82fe-217a212153ff', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-7891-4d54-af88-04eeffe136eb', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-75aa-45be-87a1-9162174da60a', 'family', '9e86a35e-76b7-4c47-a390-b54c09d0dbca', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-832e-451d-90a4-cacca413869b', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-8052-4dce-807e-fde438e4b9f5', 'education', '9e86a35e-819b-473d-910f-e027db318552', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-8d83-4c66-9c76-50d61ca83868', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-8ad4-4125-a51f-73ad9b216705', 'education', '9e86a35e-8bda-437f-99cf-951541be67c6', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-9814-46b3-9fcb-6857875942c7', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-954f-49dd-bd69-a93798ebfbd9', 'education', '9e86a35e-967c-4474-83c0-08ac0e6bc2d1', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-a382-4a1f-af86-f427a227e3a8', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-a068-4913-94ac-81dec835e433', 'employment', '9e86a35e-a1bd-463b-be95-1b62844c1b1c', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-ae21-4c1b-a578-a954b933dc91', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-ab4c-440e-a4d3-dc0e7ef8f32e', 'employment', '9e86a35e-ac57-483d-bdd0-158fa46dd965', '{}', '2025-03-26 17:43:56', '2025-03-26 17:43:56');
INSERT INTO public.connections VALUES ('9e86a35e-b8b0-497c-8ffd-49d107abe056', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-b5f7-4333-93c6-6fadef6d49a3', 'employment', '9e86a35e-b70e-4dab-87ee-cd648208ee62', '{}', '2025-03-26 17:43:57', '2025-03-26 17:43:57');
INSERT INTO public.connections VALUES ('9e86a35e-c484-46d7-b725-4018bea3c796', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-c1d9-4830-b273-c430672cb72c', 'employment', '9e86a35e-c2ed-4e51-9ed1-c7e91a0be6b6', '{}', '2025-03-26 17:43:57', '2025-03-26 17:43:57');
INSERT INTO public.connections VALUES ('9e86a35e-d088-4e96-b91d-7c101a64930c', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-cd87-434b-b11a-25ceff4786a7', 'residence', '9e86a35e-cebb-4ec3-9a29-d2e2aa2caac3', '{}', '2025-03-26 17:43:57', '2025-03-26 17:43:57');
INSERT INTO public.connections VALUES ('9e86a35e-dbcf-4ec8-b2ca-bd1f26baf5e7', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a333-af2c-414f-9dcd-54de81b91258', 'residence', '9e86a35e-d8ea-4395-8457-26bb427363c2', '{}', '2025-03-26 17:43:57', '2025-03-26 17:43:57');
INSERT INTO public.connections VALUES ('9e86a35e-e6d7-4cdb-9122-da45d6d93f84', '9e86a35e-084e-44c5-899f-64bb227f9014', '9e86a35e-e41a-4d68-9173-c5f88de70ce8', 'relationship', '9e86a35e-e54a-4873-8401-86bc6ae84708', '{}', '2025-03-26 17:43:57', '2025-03-26 17:43:57');
INSERT INTO public.connections VALUES ('9e86a30c-8d66-4f68-a72b-ca3d33199023', '9e86a30c-8627-44ed-9d04-21975f8797d6', '9e86a309-31fc-4284-8600-d3ea311a89dc', 'family', '9e86a30c-893d-4faa-87bb-646021108ae2', '{}', '2025-03-26 17:43:03', '2025-03-31 15:55:37');
INSERT INTO public.connections VALUES ('9e86a328-067c-424b-8da4-362c88eab2d0', '9e86a30c-8627-44ed-9d04-21975f8797d6', '9e86a325-6add-4436-8489-13870be94e31', 'family', '9e86a328-0273-48d5-b1be-66ebb2881819', '{}', '2025-03-26 17:43:21', '2025-03-31 15:55:37');


--
-- Data for Name: invitation_codes; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--

INSERT INTO public.invitation_codes VALUES (1, 'BETA-2024-001', false, NULL, NULL, '2025-03-28 15:18:54', '2025-03-28 15:18:54');
INSERT INTO public.invitation_codes VALUES (2, 'BETA-2024-002', false, NULL, NULL, '2025-03-28 15:18:54', '2025-03-28 15:18:54');
INSERT INTO public.invitation_codes VALUES (3, 'BETA-2024-003', false, NULL, NULL, '2025-03-28 15:18:54', '2025-03-28 15:18:54');
INSERT INTO public.invitation_codes VALUES (4, 'BETA-2024-004', false, NULL, NULL, '2025-03-28 15:18:54', '2025-03-28 15:18:54');
INSERT INTO public.invitation_codes VALUES (5, 'BETA-2024-005', false, NULL, NULL, '2025-03-28 15:18:54', '2025-03-28 15:18:54');
INSERT INTO public.invitation_codes VALUES (6, 'BETA-67E6BE113D929', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (7, 'BETA-67E6BE1140808', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (8, 'BETA-67E6BE1140A0C', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (9, 'BETA-67E6BE1140B9B', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (10, 'BETA-67E6BE1140CE9', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (11, 'BETA-67E6BE1140E09', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (12, 'BETA-67E6BE1140F53', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (13, 'BETA-67E6BE114109E', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (14, 'BETA-67E6BE11411B6', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');
INSERT INTO public.invitation_codes VALUES (15, 'BETA-67E6BE11412C7', false, NULL, NULL, '2025-03-28 15:19:45', '2025-03-28 15:19:45');


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--

INSERT INTO public.migrations VALUES (1, '2018_08_08_100000_create_telescope_entries_table', 1);
INSERT INTO public.migrations VALUES (2, '2019_12_14_000001_create_personal_access_tokens_table', 1);
INSERT INTO public.migrations VALUES (3, '2024_02_06_000000_create_temporal_constraints_table', 1);
INSERT INTO public.migrations VALUES (4, '2024_02_07_000000_create_base_schema', 1);
INSERT INTO public.migrations VALUES (5, '2024_03_19_000000_remove_redundant_span_types', 1);
INSERT INTO public.migrations VALUES (6, '2024_03_20_000000_add_thing_span_type', 1);
INSERT INTO public.migrations VALUES (7, '2024_03_20_000000_update_connection_type_allowed_spans', 1);
INSERT INTO public.migrations VALUES (8, '2024_03_20_000001_add_created_connection_type', 1);
INSERT INTO public.migrations VALUES (9, '2024_03_20_000001_create_connections_view', 1);
INSERT INTO public.migrations VALUES (10, '2024_03_20_000002_add_band_span_type', 1);
INSERT INTO public.migrations VALUES (11, '2024_03_20_000003_update_created_connection_allowed_types', 1);
INSERT INTO public.migrations VALUES (12, '2024_03_20_000004_update_membership_connection_allowed_types', 1);
INSERT INTO public.migrations VALUES (13, '2024_03_20_000005_fix_membership_connection_directions', 1);
INSERT INTO public.migrations VALUES (14, '2024_03_20_000005_update_band_span_type_metadata', 1);
INSERT INTO public.migrations VALUES (15, '2024_03_21_000000_add_contains_connection_type', 1);
INSERT INTO public.migrations VALUES (16, '2024_03_21_000000_update_connection_span_type_metadata', 1);
INSERT INTO public.migrations VALUES (17, '2024_03_21_000001_add_contains_connection_type', 1);
INSERT INTO public.migrations VALUES (18, '2024_03_21_000001_rename_connection_spans_to_spo', 1);
INSERT INTO public.migrations VALUES (19, '2024_03_21_000002_sync_connection_span_metadata', 1);
INSERT INTO public.migrations VALUES (20, '2025_02_14_000001_create_span_connections_view', 1);
INSERT INTO public.migrations VALUES (21, '2025_03_02_213453_drop_connection_unique_constraint', 1);
INSERT INTO public.migrations VALUES (22, '2025_03_02_214232_add_temporal_constraint_triggers', 1);
INSERT INTO public.migrations VALUES (23, '2025_03_03_194256_fix_temporal_constraints', 1);
INSERT INTO public.migrations VALUES (24, '2025_03_13_182254_update_span_types_use_subtype', 1);
INSERT INTO public.migrations VALUES (25, '2025_03_24_000000_add_family_connection_date_trigger', 1);
INSERT INTO public.migrations VALUES (26, '2025_03_24_000000_fix_placeholder_temporal_constraints', 1);
INSERT INTO public.migrations VALUES (27, '2025_03_24_000001_clarify_placeholder_date_validation', 1);
INSERT INTO public.migrations VALUES (28, '2024_03_27_000000_create_invitation_codes_table', 2);


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--



--
-- Data for Name: span_permissions; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--



--
-- Data for Name: span_types; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--

INSERT INTO public.span_types VALUES ('thing', 'Thing', 'A human-made item that exists in time', '{"schema": {"creator": {"type": "span", "label": "Creator", "required": true, "component": "span-input", "span_type": "person"}, "subtype": {"type": "text", "label": "Type of Thing", "options": ["book", "album", "painting", "sculpture", "other"], "required": true, "component": "select"}}}', '2025-03-26 17:42:27', '2025-03-26 17:42:27');
INSERT INTO public.span_types VALUES ('band', 'Band', 'A musical group or ensemble', '{"schema": {"genres": {"help": "Musical genres associated with this band", "type": "array", "label": "Genres", "required": false, "component": "tag-input"}, "formation_location": {"type": "span", "label": "Formation Location", "required": false, "component": "span-input", "span_type": "place"}}}', '2025-03-26 17:42:27', '2025-03-26 17:42:27');
INSERT INTO public.span_types VALUES ('person', 'Person', 'A person or individual', '{"schema": {"gender": {"help": "Gender identity", "type": "select", "label": "Gender", "options": ["male", "female", "other"], "required": false, "component": "select"}, "birth_name": {"help": "Person''s name at birth if different from primary name", "type": "text", "label": "Birth Name", "required": false, "component": "text-input"}, "occupation": {"help": "Main occupation or role", "type": "text", "label": "Primary Occupation", "required": false, "component": "text-input"}, "nationality": {"help": "Primary nationality", "type": "text", "label": "Nationality", "required": false, "component": "text-input"}}}', '2025-03-26 17:42:26', '2025-03-26 17:42:26');
INSERT INTO public.span_types VALUES ('organisation', 'Organisation', 'An organization or institution', '{"schema": {"size": {"help": "Size of organization", "type": "select", "label": "Size", "options": ["small", "medium", "large"], "required": false, "component": "select"}, "subtype": {"help": "Type of organization", "type": "select", "label": "Organisation Type", "options": ["business", "educational", "government", "non-profit", "religious", "other"], "required": true, "component": "select"}, "industry": {"help": "Primary industry or sector", "type": "text", "label": "Industry", "required": false, "component": "text-input"}}}', '2025-03-26 17:42:26', '2025-03-26 17:42:26');
INSERT INTO public.span_types VALUES ('event', 'Event', 'A historical or personal event', '{"schema": {"subtype": {"help": "Type of event", "type": "select", "label": "Event Type", "options": ["personal", "historical", "cultural", "political", "other"], "required": true, "component": "select"}, "location": {"help": "Where the event took place", "type": "text", "label": "Location", "required": false, "component": "text-input"}, "significance": {"help": "Why this event is significant", "type": "text", "label": "Significance", "required": false, "component": "text-input"}}}', '2025-03-26 17:42:26', '2025-03-26 17:42:26');
INSERT INTO public.span_types VALUES ('place', 'Place', 'A physical location or place', '{"schema": {"country": {"help": "Country where this place is located", "type": "text", "label": "Country", "required": false, "component": "text-input"}, "subtype": {"help": "Type of place", "type": "select", "label": "Place Type", "options": ["city", "country", "region", "building", "landmark", "other"], "required": true, "component": "select"}, "coordinates": {"help": "Geographic coordinates", "type": "text", "label": "Coordinates", "required": false, "component": "text-input"}}}', '2025-03-26 17:42:26', '2025-03-26 17:42:26');
INSERT INTO public.span_types VALUES ('connection', 'Connection', 'A temporal connection between spans', '{"schema": {"role": {"help": "Role or position in this connection", "type": "text", "label": "Role", "required": false, "component": "text-input"}, "notes": {"help": "Additional notes about this connection", "type": "textarea", "label": "Notes", "required": false, "component": "textarea"}, "connection_type": {"help": "Type of connection", "type": "select", "label": "Connection Type", "options": ["attendance", "contains", "created", "relationship", "family", "residence", "ownership", "participation", "education", "travel", "membership", "employment"], "required": true, "component": "select"}}}', '2025-03-26 17:42:26', '2025-03-26 17:42:27');


--
-- Data for Name: spans; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--

INSERT INTO public.spans VALUES ('3944dfe6-8368-4934-b5ad-ffc81894a362', 'System', 'system', 'person', true, NULL, NULL, 2024, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'draft', NULL, NULL, '{}', NULL, 420, 'own', 'private', 'd599225a-a3d3-41e9-a4eb-955bbc5ed446', 'd599225a-a3d3-41e9-a4eb-955bbc5ed446', '2025-03-26 17:42:27', '2025-03-26 17:42:27', NULL);
INSERT INTO public.spans VALUES ('9e86a2fd-1f7b-451f-a14e-3893c1097d76', 'Agnes Northover', 'agnes-northover', 'person', false, NULL, NULL, 2014, 4, 7, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:42:53', NULL);
INSERT INTO public.spans VALUES ('9e86a2fd-371b-4f81-879f-c6f52eb4dcd3', 'Richard Northover is family of Agnes Northover', 'richard-northover-is-family-of-agnes-northover', 'connection', false, NULL, NULL, 2014, 4, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a2fe-1cb9-4b1f-a3e2-105ff9540135', 'Aiden Northover', 'aiden-northover', 'person', false, NULL, NULL, 2023, 12, 1, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:42:53', NULL);
INSERT INTO public.spans VALUES ('9e86a2fe-334f-46dc-acfb-79ac5489643d', 'Tom Northover', 'tom-northover', 'person', false, NULL, NULL, 1989, 1, 23, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-1d93-44ee-9f4e-2119c17ef9a6', 'Alan Turing', 'alan-turing', 'person', false, NULL, NULL, 1912, 6, 23, 1954, 6, 7, 'day', 'day', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-24e8-4a76-930e-1170d34865d7', 'Alan Turing studied at St Michael''s, a preparatory school in the seaside town of St Leonards-on-Sea, Hastings, England', 'alan-turing-studied-at-st-michaels-a-preparatory-school-in-the-seaside-town-of-st-leonards-on-sea-hastings-england', 'connection', false, NULL, NULL, 1922, NULL, NULL, 1926, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-366b-46b7-9d94-7f73a64dad5a', 'Sherborne School', 'sherborne-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-3d5a-4c88-a25d-09beb1cf97c5', 'Alan Turing studied at Sherborne School', 'alan-turing-studied-at-sherborne-school', 'connection', false, NULL, NULL, 1926, 9, NULL, 1931, 7, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-4d6e-468b-a0f1-048a56f99c57', 'King''s College, University of Cambridge', 'kings-college-university-of-cambridge', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2fe-2109-419c-88e2-cb1e41ccd40b', 'Arielle Northover', 'arielle-northover', 'person', false, NULL, NULL, 1992, 3, 23, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:42:58', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-520d-4bd3-845b-13a87530a118', 'Alan Turing studied at King''s College, University of Cambridge', 'alan-turing-studied-at-kings-college-university-of-cambridge', 'connection', false, NULL, NULL, 1934, NULL, NULL, 1936, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-6956-4521-8597-4070b52b60f1', 'Princeton University', 'princeton-university', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-6bf3-477f-b8a5-e58ba2c134d6', 'Alan Turing studied at Princeton University', 'alan-turing-studied-at-princeton-university', 'connection', false, NULL, NULL, 1936, NULL, NULL, 1938, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-7663-4036-aef0-e484f9cd3df1', 'Government Code and Cypher School at Bletchley Park', 'government-code-and-cypher-school-at-bletchley-park', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Cryptanalyst"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-778c-47d1-adef-0156b9528efd', 'Alan Turing worked at Government Code and Cypher School at Bletchley Park', 'alan-turing-worked-at-government-code-and-cypher-school-at-bletchley-park', 'connection', false, NULL, NULL, 1939, NULL, NULL, 1945, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Cryptanalyst", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2fe-250d-4c70-bb1e-106bfae13411', 'Arielle Northover is family of Aiden Northover', 'arielle-northover-is-family-of-aiden-northover', 'connection', false, NULL, NULL, 2023, 12, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:42:58', NULL);
INSERT INTO public.spans VALUES ('9e86a2fd-2311-40d2-ac40-e6761c9b4504', 'Jenny McInnes', 'jenny-mcinnes', 'person', false, NULL, NULL, 1980, 6, 6, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a2fd-2639-4874-8e77-ab9e60c9252c', 'Jenny McInnes is family of Agnes Northover', 'jenny-mcinnes-is-family-of-agnes-northover', 'connection', false, NULL, NULL, 2014, 4, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', 'Richard Northover', 'richard-northover', 'person', false, NULL, NULL, 1976, 2, 13, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:27', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a2fe-353e-4443-a062-3c7b975c8c2a', 'Tom Northover is family of Aiden Northover', 'tom-northover-is-family-of-aiden-northover', 'connection', false, NULL, NULL, 2023, 12, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:53', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-7eae-4cd1-b65f-9f79b0a41da9', 'Computing Laboratory at the University of Manchester', 'computing-laboratory-at-the-university-of-manchester', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Deputy Director"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-805a-4d1e-b1e1-ace1be155665', 'Alan Turing worked at Computing Laboratory at the University of Manchester', 'alan-turing-worked-at-computing-laboratory-at-the-university-of-manchester', 'connection', false, NULL, NULL, 1948, NULL, NULL, 1954, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Deputy Director", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-87df-4c7e-8868-ce71214f97b7', 'Maida Vale, London, England', 'maida-vale-london-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-899d-49b2-bf43-0ab5e177f9cc', 'Alan Turing lived in Maida Vale, London, England', 'alan-turing-lived-in-maida-vale-london-england', 'connection', false, NULL, NULL, 1912, 6, 23, 1926, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-8ff6-461c-a58c-e71f097e7719', 'Sherborne, Dorset, England', 'sherborne-dorset-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-914f-46e8-b2fa-f6b9a4a92766', 'Alan Turing lived in Sherborne, Dorset, England', 'alan-turing-lived-in-sherborne-dorset-england', 'connection', false, NULL, NULL, 1926, NULL, NULL, 1931, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-97a4-481d-b589-b5a8de29e817', 'Cambridge, England', 'cambridge-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a302-0711-4cbc-afc8-9466c9ea8345', 'Garvey Northover', 'garvey-northover', 'person', false, NULL, NULL, 2011, 7, 14, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:43:08', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-98af-4386-a375-86604d4ae3d3', 'Alan Turing lived in Cambridge, England', 'alan-turing-lived-in-cambridge-england', 'connection', false, NULL, NULL, 1931, NULL, NULL, 1936, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-9c3e-46c2-95eb-eecc800e79e3', 'Princeton, New Jersey, United States', 'princeton-new-jersey-united-states', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-9d4f-4f97-b500-9302ab0dc1fa', 'Alan Turing lived in Princeton, New Jersey, United States', 'alan-turing-lived-in-princeton-new-jersey-united-states', 'connection', false, NULL, NULL, 1936, NULL, NULL, 1938, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-a0c2-4681-9ab8-8ffbc59dc756', 'Bletchley Park, England', 'bletchley-park-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-a1dc-493a-9de0-a54db5489b56', 'Alan Turing lived in Bletchley Park, England', 'alan-turing-lived-in-bletchley-park-england', 'connection', false, NULL, NULL, 1939, NULL, NULL, 1945, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-a5e2-4f73-a894-470bbd0566b9', 'Manchester, England', 'manchester-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-a761-4a41-917b-fbea519b7531', 'Alan Turing lived in Manchester, England', 'alan-turing-lived-in-manchester-england', 'connection', false, NULL, NULL, 1948, NULL, NULL, 1954, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-ace5-45d4-b13b-188466075baa', 'Joan Clarke', 'joan-clarke', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-ae05-4367-a2fa-697b855fa34a', 'Alan Turing has relationship with Joan Clarke', 'alan-turing-has-relationship-with-joan-clarke', 'connection', false, NULL, NULL, 1941, NULL, NULL, 1941, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-26 17:42:54', NULL);
INSERT INTO public.spans VALUES ('9e86a300-8fe5-4e92-a1fa-ae42b0e7a125', 'Albert Einstein', 'albert-einstein', 'person', false, NULL, NULL, 1879, 3, 14, 1955, 4, 18, 'day', 'day', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-921a-43e0-a973-ae67e2dd798c', 'Aarau Cantonal School', 'aarau-cantonal-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-9441-46fc-a69c-f36b1690fa8c', 'Albert Einstein studied at Aarau Cantonal School', 'albert-einstein-studied-at-aarau-cantonal-school', 'connection', false, NULL, NULL, 1895, NULL, NULL, 1896, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-9fe8-4d47-9f0a-39d7ff96b798', 'ETH Zurich (Swiss Federal Polytechnic)', 'eth-zurich-swiss-federal-polytechnic', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "tertiary", "course": "Diploma in Teaching Physics and Mathematics"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-a18f-4a3c-a280-e9163188a37e', 'Albert Einstein studied at ETH Zurich (Swiss Federal Polytechnic)', 'albert-einstein-studied-at-eth-zurich-swiss-federal-polytechnic', 'connection', false, NULL, NULL, 1896, NULL, NULL, 1900, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "tertiary", "course": "Diploma in Teaching Physics and Mathematics", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-a97a-4583-8d3f-1e8b84809ed4', 'Swiss Patent Office', 'swiss-patent-office', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Patent Examiner"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-ac9a-4a02-aa3b-80ee01bca10d', 'Albert Einstein worked at Swiss Patent Office', 'albert-einstein-worked-at-swiss-patent-office', 'connection', false, NULL, NULL, 1902, NULL, NULL, 1909, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Patent Examiner", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-b44a-4f84-a861-983785bbb0b6', 'University of Zurich', 'university-of-zurich', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor of Theoretical Physics"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-b5c5-46f5-b653-bbcf75c79340', 'Albert Einstein worked at University of Zurich', 'albert-einstein-worked-at-university-of-zurich', 'connection', false, NULL, NULL, 1909, NULL, NULL, 1911, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor of Theoretical Physics", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-bcff-4134-98a7-a3fc6bafea4f', 'Charles University in Prague', 'charles-university-in-prague', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor of Theoretical Physics"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-bf7a-45ce-a028-f6727220489f', 'Albert Einstein worked at Charles University in Prague', 'albert-einstein-worked-at-charles-university-in-prague', 'connection', false, NULL, NULL, 1911, NULL, NULL, 1912, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor of Theoretical Physics", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-c702-452c-9dea-2b393a9cefda', 'ETH Zurich', 'eth-zurich', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor of Theoretical Physics"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-c85a-4eb3-870a-2498ef0ccbb9', 'Albert Einstein worked at ETH Zurich', 'albert-einstein-worked-at-eth-zurich', 'connection', false, NULL, NULL, 1912, NULL, NULL, 1914, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor of Theoretical Physics", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-d1a5-435b-a1d4-65820bbc87a2', 'Kaiser Wilhelm Institute for Physics', 'kaiser-wilhelm-institute-for-physics', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor and Director of Physics"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-d30a-41b1-a803-b95268e12f3e', 'Albert Einstein worked at Kaiser Wilhelm Institute for Physics', 'albert-einstein-worked-at-kaiser-wilhelm-institute-for-physics', 'connection', false, NULL, NULL, 1914, NULL, NULL, 1933, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor and Director of Physics", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-d949-4109-9f90-c332f79e2f00', 'Institute for Advanced Study, Princeton', 'institute-for-advanced-study-princeton', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Professor of Theoretical Physics"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-da9d-4899-9115-08ec9c441420', 'Albert Einstein worked at Institute for Advanced Study, Princeton', 'albert-einstein-worked-at-institute-for-advanced-study-princeton', 'connection', false, NULL, NULL, 1933, NULL, NULL, 1955, 4, 18, 'year', 'day', 'placeholder', NULL, NULL, '{"role": "Professor of Theoretical Physics", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-e180-4d19-bb25-8414589d190a', 'Albert Einstein lived in Ulm, Kingdom of Württemberg, German Empire', 'albert-einstein-lived-in-ulm-kingdom-of-wurttemberg-german-empire', 'connection', false, NULL, NULL, 1879, 3, 14, 1880, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-e8a1-4440-b355-e8627b6aa81e', 'Munich, Germany', 'munich-germany', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-ea09-4321-a458-5ee42720a782', 'Albert Einstein lived in Munich, Germany', 'albert-einstein-lived-in-munich-germany', 'connection', false, NULL, NULL, 1880, NULL, NULL, 1895, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-ef9d-45e8-941c-dca040e2c386', 'Zurich, Switzerland', 'zurich-switzerland', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-f103-456c-94ec-35e91690f0d7', 'Albert Einstein lived in Zurich, Switzerland', 'albert-einstein-lived-in-zurich-switzerland', 'connection', false, NULL, NULL, 1896, NULL, NULL, 1902, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-f7f8-4f5b-8aac-3477c692aaa3', 'Bern, Switzerland', 'bern-switzerland', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-f93d-45c7-96d3-26cc43daa0cb', 'Albert Einstein lived in Bern, Switzerland', 'albert-einstein-lived-in-bern-switzerland', 'connection', false, NULL, NULL, 1902, NULL, NULL, 1909, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-fea9-4152-8322-eb500eca41dc', 'Princeton, New Jersey, USA', 'princeton-new-jersey-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a300-ffe4-4e29-b4cc-2c9f828f74d4', 'Albert Einstein lived in Princeton, New Jersey, USA', 'albert-einstein-lived-in-princeton-new-jersey-usa', 'connection', false, NULL, NULL, 1933, NULL, NULL, 1955, 4, 18, 'year', 'day', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a301-0630-43a4-a4b9-4aa44b3e0be1', 'Mileva Marić', 'mileva-maric', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a301-076d-431c-ad8d-fa160ed92577', 'Albert Einstein has relationship with Mileva Marić', 'albert-einstein-has-relationship-with-mileva-maric', 'connection', false, NULL, NULL, 1903, NULL, NULL, 1919, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a301-0cc1-4bab-bd4c-82dccc4289f3', 'Elsa Einstein', 'elsa-einstein', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a301-0e14-4b80-8f38-b207528b6699', 'Albert Einstein has relationship with Elsa Einstein', 'albert-einstein-has-relationship-with-elsa-einstein', 'connection', false, NULL, NULL, 1919, NULL, NULL, 1936, 12, 20, 'year', 'day', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-26 17:42:55', NULL);
INSERT INTO public.spans VALUES ('9e86a301-ed9e-4a34-9349-005a8a05fc2c', 'Ali Northover', 'ali-northover', 'person', false, NULL, NULL, 1973, 7, 12, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:42:56', NULL);
INSERT INTO public.spans VALUES ('9e86a302-fd8c-4496-9453-5dad183b6c66', 'Louisa Denison', 'louisa-denison', 'person', false, NULL, NULL, 1993, 3, 28, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:43:29', NULL);
INSERT INTO public.spans VALUES ('9e86a302-fa32-4e77-a850-a461d0b3bc4b', 'Amelia Denison', 'amelia-denison', 'person', false, NULL, NULL, 2024, 1, 1, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:42:56', NULL);
INSERT INTO public.spans VALUES ('9e86a303-1313-4862-8281-d4f0dd4ff5fd', 'Robbie Denison', 'robbie-denison', 'person', false, NULL, NULL, 1994, 1, 11, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:43:43', NULL);
INSERT INTO public.spans VALUES ('9e86a301-f1bb-45dc-a729-e7ce5c688c3e', 'Scott Northover', 'scott-northover', 'person', false, NULL, NULL, 2009, 6, 9, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:43:43', NULL);
INSERT INTO public.spans VALUES ('9e86a306-e569-4694-b81c-cd79e6bd409f', 'Barack Obama', 'barack-obama', 'person', false, NULL, NULL, 1961, 8, 4, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a306-e94d-483f-be6f-6e73fd49e6bf', 'Stanley Ann Dunham', 'stanley-ann-dunham', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a306-ecab-4e4f-bc78-6dc7ddb39c47', 'Stanley Ann Dunham is family of Barack Obama', 'stanley-ann-dunham-is-family-of-barack-obama', 'connection', false, NULL, NULL, 1961, 8, 4, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a306-ffaf-4a34-b054-b57a97010baa', 'Barack Obama Sr.', 'barack-obama-sr', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-03f9-42af-be45-d7c1a9a52f75', 'Barack Obama Sr. is family of Barack Obama', 'barack-obama-sr-is-family-of-barack-obama', 'connection', false, NULL, NULL, 1961, 8, 4, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-107c-4570-9db9-87ea3e17042d', 'Malia Ann Obama', 'malia-ann-obama', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-12ee-42e8-be78-0ea75ea11191', 'Barack Obama is family of Malia Ann Obama', 'barack-obama-is-family-of-malia-ann-obama', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-1bcb-41d5-91b6-e7304974c846', 'Natasha Marian Obama', 'natasha-marian-obama', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-1e0a-4302-8415-48bc4f99d5d2', 'Barack Obama is family of Natasha Marian Obama', 'barack-obama-is-family-of-natasha-marian-obama', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-2554-45b9-98e2-16525d7314cf', 'Punahou School', 'punahou-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-274e-4bef-9e53-3c4e7daa5eb6', 'Barack Obama studied at Punahou School', 'barack-obama-studied-at-punahou-school', 'connection', false, NULL, NULL, 1971, NULL, NULL, 1979, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-2e8b-4431-94a1-981b20d6294c', 'Occidental College', 'occidental-college', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-6241-4090-8958-6454b30cea03', 'University of Minnesota', 'university-of-minnesota', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-64f4-43c3-a86f-30467d9006e9', 'Bob Dylan studied at University of Minnesota', 'bob-dylan-studied-at-university-of-minnesota', 'connection', false, NULL, NULL, 1959, NULL, NULL, 1960, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-6ec3-456c-a2a6-fc31a6dc04b7', 'Duluth, Minnesota, USA', 'duluth-minnesota-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-7136-4394-b7c6-29d3c9e4e2f6', 'Bob Dylan lived in Duluth, Minnesota, USA', 'bob-dylan-lived-in-duluth-minnesota-usa', 'connection', false, NULL, NULL, 1941, 5, 24, 1959, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-7a00-44ec-b4d6-255894d5922b', 'Minneapolis, Minnesota, USA', 'minneapolis-minnesota-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a303-022e-44ed-8536-f4cd4a723199', 'Louisa Denison is family of Amelia Denison', 'louisa-denison-is-family-of-amelia-denison', 'connection', false, NULL, NULL, 2024, 1, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:43:29', NULL);
INSERT INTO public.spans VALUES ('9e86a303-1658-4036-afd8-d6f15426df52', 'Robbie Denison is family of Amelia Denison', 'robbie-denison-is-family-of-amelia-denison', 'connection', false, NULL, NULL, 2024, 1, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:43:43', NULL);
INSERT INTO public.spans VALUES ('9e86a301-f4f4-42fe-9baa-9696171dcf83', 'Ali Northover is family of Scott Northover', 'ali-northover-is-family-of-scott-northover', 'connection', false, NULL, NULL, 2009, 6, 9, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:43:43', NULL);
INSERT INTO public.spans VALUES ('9e86a307-3048-4c86-af18-16a1f9bb6f2b', 'Barack Obama studied at Occidental College', 'barack-obama-studied-at-occidental-college', 'connection', false, NULL, NULL, 1979, NULL, NULL, 1981, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-3730-4e34-b4ee-0de18354b029', 'Columbia University', 'columbia-university', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-38ca-4dc9-a3a4-0e69888a575e', 'Barack Obama studied at Columbia University', 'barack-obama-studied-at-columbia-university', 'connection', false, NULL, NULL, 1981, NULL, NULL, 1983, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-3f0f-42a7-a74a-ad4a1a1535b2', 'Harvard Law School', 'harvard-law-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "graduate"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-4089-4b15-b6c0-d4324d6627a2', 'Barack Obama studied at Harvard Law School', 'barack-obama-studied-at-harvard-law-school', 'connection', false, NULL, NULL, 1988, NULL, NULL, 1991, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "graduate", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-46be-42f5-87fb-62f0731ec4fd', 'United States Government', 'united-states-government', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "President of the United States"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a309-19a4-4fdb-8c27-1c506cc50052', 'Katy Northover', 'katy-northover', 'person', false, NULL, NULL, 1948, 10, 9, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:00', '2025-03-26 17:43:23', NULL);
INSERT INTO public.spans VALUES ('9e86a307-4837-4cc3-bc53-d0c37b06db21', 'Barack Obama worked at United States Government', 'barack-obama-worked-at-united-states-government', 'connection', false, NULL, NULL, 2005, 1, 3, 2008, 11, 16, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "U.S. Senator from Illinois", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-500d-4bcc-902d-d952dd40dde0', 'Honolulu, Hawaii, USA', 'honolulu-hawaii-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "birthplace and childhood home"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-5198-463d-a0d4-63eab62510b9', 'Barack Obama lived in Honolulu, Hawaii, USA', 'barack-obama-lived-in-honolulu-hawaii-usa', 'connection', false, NULL, NULL, 1961, 8, 4, 1979, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"reason": "birthplace and childhood home", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-5734-48b8-a675-9844a38e60e2', 'Chicago, Illinois, USA', 'chicago-illinois-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "work and family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-5876-495e-957b-2ccac25d1468', 'Barack Obama lived in Chicago, Illinois, USA', 'barack-obama-lived-in-chicago-illinois-usa', 'connection', false, NULL, NULL, 1985, NULL, NULL, 2009, 1, 20, 'year', 'day', 'placeholder', NULL, NULL, '{"reason": "work and family", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-5db0-4757-96f3-fb0899af8a8f', 'Michelle Obama', 'michelle-obama', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a307-5ef8-4fd6-a9bc-4d71f9d09089', 'Barack Obama has relationship with Michelle Obama', 'barack-obama-has-relationship-with-michelle-obama', 'connection', false, NULL, NULL, 1992, 10, 3, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:59', '2025-03-26 17:42:59', NULL);
INSERT INTO public.spans VALUES ('9e86a308-3bbd-4573-aac2-5b8ae745fbf8', 'Ben Martynoga', 'ben-martynoga', 'person', false, NULL, NULL, 1979, 6, 26, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:00', '2025-03-26 17:43:00', NULL);
INSERT INTO public.spans VALUES ('9e86a309-15d9-4252-a7f5-19573d14d193', 'Benn Northover', 'benn-northover', 'person', false, NULL, NULL, 1978, 1, 3, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:00', '2025-03-26 17:43:00', NULL);
INSERT INTO public.spans VALUES ('9e86a309-44ef-4098-9452-983f2809bebe', 'Pema Northover', 'pema-northover', 'person', false, NULL, NULL, 2013, 1, 29, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:00', '2025-03-26 17:43:36', NULL);
INSERT INTO public.spans VALUES ('9e86a30a-3b45-4452-b5bf-9a6aa91b3ae9', 'Beth Gibbons', 'beth-gibbons', 'person', false, NULL, NULL, 1965, 1, 4, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:01', '2025-03-26 17:43:01', NULL);
INSERT INTO public.spans VALUES ('9e86a30a-3e96-4b9b-81c1-eb720b76f11d', 'Portishead', 'portishead', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Vocalist"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:01', '2025-03-26 17:43:01', NULL);
INSERT INTO public.spans VALUES ('9e86a30a-421d-4eea-ab2e-8cf41a5d520b', 'Beth Gibbons worked at Portishead', 'beth-gibbons-worked-at-portishead', 'connection', false, NULL, NULL, 1991, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Vocalist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:01', '2025-03-26 17:43:01', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-37fd-4d68-a2bd-6b386834057d', 'Bob Dylan', 'bob-dylan', 'person', false, NULL, NULL, 1941, 5, 24, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-3c3a-4bc9-82bf-eed780b945b9', 'Beatty Zimmerman', 'beatty-zimmerman', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-4077-4733-831f-d37bd00a8d13', 'Beatty Zimmerman is family of Bob Dylan', 'beatty-zimmerman-is-family-of-bob-dylan', 'connection', false, NULL, NULL, 1941, 5, 24, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-52b6-4281-8639-21fe10c3e2d8', 'Abram Zimmerman', 'abram-zimmerman', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-55c3-49fd-878a-bf444e462619', 'Abram Zimmerman is family of Bob Dylan', 'abram-zimmerman-is-family-of-bob-dylan', 'connection', false, NULL, NULL, 1941, 5, 24, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a309-3602-4589-9683-90ea1ee95d2b', 'Chris Northover is family of Benn Northover', 'chris-northover-is-family-of-benn-northover', 'connection', false, NULL, NULL, 1978, 1, 3, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:00', '2025-03-26 17:43:03', NULL);
INSERT INTO public.spans VALUES ('9e86a309-1db4-4db9-a325-9cd7be6416cf', 'Katy Northover is family of Benn Northover', 'katy-northover-is-family-of-benn-northover', 'connection', false, NULL, NULL, 1978, 1, 3, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:00', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-7c26-4223-b0f5-868704b6d4e8', 'Bob Dylan lived in Minneapolis, Minnesota, USA', 'bob-dylan-lived-in-minneapolis-minnesota-usa', 'connection', false, NULL, NULL, 1959, NULL, NULL, 1960, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-845a-4797-9d5c-98f81bcd02bb', 'New York City, New York, USA', 'new-york-city-new-york-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30b-8623-443d-96c9-9c3ef1cd1e72', 'Bob Dylan lived in New York City, New York, USA', 'bob-dylan-lived-in-new-york-city-new-york-usa', 'connection', false, NULL, NULL, 1961, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:02', '2025-03-26 17:43:02', NULL);
INSERT INTO public.spans VALUES ('9e86a30f-505b-4534-836c-6a8d11261032', 'Richard Northover is family of Danny Northover', 'richard-northover-is-family-of-danny-northover', 'connection', false, NULL, NULL, 2011, 6, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:04', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a30c-7589-43b3-accc-a37751edb5ba', 'Peggy Northover', 'peggy-northover', 'person', false, NULL, NULL, 1920, 1, 14, 2014, 7, 15, 'day', 'day', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:03', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a30f-40af-4ad1-9bce-800217686eaf', 'Jenny McInnes is family of Danny Northover', 'jenny-mcinnes-is-family-of-danny-northover', 'connection', false, NULL, NULL, 2011, 6, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:04', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a30e-5e54-4f1b-a241-8e180345e3c8', 'Danni Northover', 'danni-northover', 'person', false, NULL, NULL, 1992, 5, 26, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:04', '2025-03-26 17:43:04', NULL);
INSERT INTO public.spans VALUES ('9e86a30f-3b47-4cb9-89b6-317c113b4f05', 'Danny Northover', 'danny-northover', 'person', false, NULL, NULL, 2011, 6, 7, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:04', '2025-03-26 17:43:04', NULL);
INSERT INTO public.spans VALUES ('9e86a310-35a2-4fe0-8fc1-cc6ad7546cb3', 'David Attenborough', 'david-attenborough', 'person', false, NULL, NULL, 1926, 5, 8, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-38f7-4a3f-a296-f9cc974ba12c', 'Mary Attenborough', 'mary-attenborough', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-3c7e-4bdf-9700-8c031bd34ecb', 'Mary Attenborough is family of David Attenborough', 'mary-attenborough-is-family-of-david-attenborough', 'connection', false, NULL, NULL, 1926, 5, 8, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-4b25-47a1-af05-2cd884a1e92f', 'Frederick Attenborough', 'frederick-attenborough', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-4d5c-4bf0-a641-4e0254a3549e', 'Frederick Attenborough is family of David Attenborough', 'frederick-attenborough-is-family-of-david-attenborough', 'connection', false, NULL, NULL, 1926, 5, 8, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-580b-411a-9485-6c8107db65ee', 'Robert Attenborough', 'robert-attenborough', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-5a4e-4a68-bc45-428814b306f2', 'David Attenborough is family of Robert Attenborough', 'david-attenborough-is-family-of-robert-attenborough', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-6442-47c1-bf41-c07da95b7124', 'Susan Attenborough', 'susan-attenborough', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-6676-4795-a7da-391c905b6f78', 'David Attenborough is family of Susan Attenborough', 'david-attenborough-is-family-of-susan-attenborough', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-6f13-41ea-b122-d91871a5b4c2', 'Helga Attenborough', 'helga-attenborough', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-70ec-40a2-9cdc-dc3a4489eb75', 'David Attenborough is family of Helga Attenborough', 'david-attenborough-is-family-of-helga-attenborough', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-7911-46c1-94e0-7b08f0a5c2c8', 'University College, London', 'university-college-london', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a313-4866-4a59-a08c-889c9fe48609', 'Barron Trump', 'barron-trump', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-4a18-4f3c-b888-bff8c4b88ecd', 'Donald Trump is family of Barron Trump', 'donald-trump-is-family-of-barron-trump', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-5194-48dd-8eca-64862cc7f7ca', 'Fordham University', 'fordham-university', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "higher education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-5330-4263-9c2e-d6fcfba441e7', 'Donald Trump studied at Fordham University', 'donald-trump-studied-at-fordham-university', 'connection', false, NULL, NULL, 1964, NULL, NULL, 1966, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "higher education", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a30c-9cf7-4533-b8b1-08bf0e11c8e0', 'Jack Northover', 'jack-northover', 'person', false, NULL, NULL, 1982, 11, 7, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:03', '2025-03-26 17:43:15', NULL);
INSERT INTO public.spans VALUES ('9e86a310-7ade-4c15-9f9b-9a957e84c13c', 'David Attenborough studied at University College, London', 'david-attenborough-studied-at-university-college-london', 'connection', false, NULL, NULL, 1945, NULL, NULL, 1947, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-824c-4e37-9ba1-0c22c24fd541', 'University of Cambridge', 'university-of-cambridge', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-83e7-417d-9e8e-42067d7949b7', 'David Attenborough studied at University of Cambridge', 'david-attenborough-studied-at-university-of-cambridge', 'connection', false, NULL, NULL, 1947, NULL, NULL, 1950, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-8ae4-48a7-94cb-4cc383af2b83', 'BBC', 'bbc', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Producer"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a311-aa0e-4ee3-964b-38472ba1c1b1', 'Haywood Stenton Jones is family of David Bowie', 'haywood-stenton-jones-is-family-of-david-bowie', 'connection', false, NULL, NULL, 1947, 1, 8, 2016, 1, 10, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a310-8c6f-4e75-8a13-45f9a501f9a0', 'David Attenborough worked at BBC', 'david-attenborough-worked-at-bbc', 'connection', false, NULL, NULL, 1969, NULL, NULL, 1972, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Director of Programmes, Television", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-9703-4c65-86e3-aafac24d8514', 'David Attenborough', 'david-attenborough-2', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Freelance Broadcaster and Naturalist"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-986d-4805-be01-0653315d668a', 'David Attenborough worked at David Attenborough', 'david-attenborough-worked-at-david-attenborough', 'connection', false, NULL, NULL, 1972, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Freelance Broadcaster and Naturalist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-9eba-43b3-a0e3-d7fc84fa15e1', 'London, United Kingdom', 'london-united-kingdom', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-a01c-48b1-8dd6-3bae61923142', 'David Attenborough lived in London, United Kingdom', 'david-attenborough-lived-in-london-united-kingdom', 'connection', false, NULL, NULL, 1926, 5, 8, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-a631-434b-985a-a254fb9ed460', 'Jane Elizabeth Ebsworth Oriel', 'jane-elizabeth-ebsworth-oriel', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a310-a7a2-40eb-be30-81509972b674', 'David Attenborough has relationship with Jane Elizabeth Ebsworth Oriel', 'david-attenborough-has-relationship-with-jane-elizabeth-ebsworth-oriel', 'connection', false, NULL, NULL, 1950, NULL, NULL, 1997, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:05', '2025-03-26 17:43:05', NULL);
INSERT INTO public.spans VALUES ('9e86a311-8b42-490a-b0c5-0af83de1276c', 'David Bowie', 'david-bowie', 'person', false, NULL, NULL, 1947, 1, 8, 2016, 1, 10, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-8ded-47bb-b52f-66332e4a5b66', 'Peggy Jones', 'peggy-jones', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-9134-454d-85aa-23a7e88d1dc6', 'Peggy Jones is family of David Bowie', 'peggy-jones-is-family-of-david-bowie', 'connection', false, NULL, NULL, 1947, 1, 8, 2016, 1, 10, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-a662-4a53-abf7-c00209cf2c3b', 'Haywood Stenton Jones', 'haywood-stenton-jones', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-b8ee-4504-bc1d-f9e3be47cc04', 'Duncan Jones', 'duncan-jones', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-bb97-4484-89e3-5512248b1343', 'David Bowie is family of Duncan Jones', 'david-bowie-is-family-of-duncan-jones', 'connection', false, NULL, NULL, NULL, NULL, NULL, 2016, 1, 10, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-c8bd-4d80-b40b-ff69ed9f7a53', 'Alexandria Zahra Jones', 'alexandria-zahra-jones', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-cae7-4095-a108-585bb5dcd095', 'David Bowie is family of Alexandria Zahra Jones', 'david-bowie-is-family-of-alexandria-zahra-jones', 'connection', false, NULL, NULL, NULL, NULL, NULL, 2016, 1, 10, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-d528-414b-80ab-2de9dcdf1b73', 'Bromley Technical High School', 'bromley-technical-high-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "course": "Art"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a316-8736-489c-8b06-72309c6577b9', 'George Harrison is family of Dhani Harrison', 'george-harrison-is-family-of-dhani-harrison', 'connection', false, NULL, NULL, NULL, NULL, NULL, 2001, 11, 29, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-95b4-4333-9ab9-5fbbaaf58103', 'Liverpool Institute High School for Boys', 'liverpool-institute-high-school-for-boys', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-9861-4c04-9e9f-ee929ad633be', 'George Harrison studied at Liverpool Institute High School for Boys', 'george-harrison-studied-at-liverpool-institute-high-school-for-boys', 'connection', false, NULL, NULL, 1954, NULL, NULL, 1959, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-a3be-49ca-bc73-2144e7a865e4', 'The Beatles', 'the-beatles', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Guitarist"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-a601-4ba8-912b-74b9ba192988', 'George Harrison worked at The Beatles', 'george-harrison-worked-at-the-beatles', 'connection', false, NULL, NULL, 1960, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Guitarist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a311-d720-4fcd-ac25-fa5f40f6653e', 'David Bowie studied at Bromley Technical High School', 'david-bowie-studied-at-bromley-technical-high-school', 'connection', false, NULL, NULL, 1958, NULL, NULL, 1963, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "course": "Art", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-dfbc-4140-b974-f061c264f17b', 'Self-Employed', 'self-employed', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Musician"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-e186-4bae-9288-2c87e72b5f20', 'David Bowie worked at Self-Employed', 'david-bowie-worked-at-self-employed', 'connection', false, NULL, NULL, 1962, NULL, NULL, 2016, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Musician", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-e9a2-4549-a72e-bace81e512c0', 'Brixton, London, UK', 'brixton-london-uk', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Birthplace"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-eb75-42b0-9ac7-81b38ed15afd', 'David Bowie lived in Brixton, London, UK', 'david-bowie-lived-in-brixton-london-uk', 'connection', false, NULL, NULL, 1947, NULL, NULL, 1953, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Birthplace", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-f3b3-4a1b-9cc3-f02c91c4112a', 'Bromley, London, UK', 'bromley-london-uk', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Childhood home"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-f54b-4b49-b246-dfcc3033eda3', 'David Bowie lived in Bromley, London, UK', 'david-bowie-lived-in-bromley-london-uk', 'connection', false, NULL, NULL, 1953, NULL, NULL, 1962, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Childhood home", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-fc84-487c-9bb5-bf1e220e3634', 'New York, USA', 'new-york-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Final residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a311-fe07-4f32-aed9-8e2329ddd96e', 'David Bowie lived in New York, USA', 'david-bowie-lived-in-new-york-usa', 'connection', false, NULL, NULL, 1993, NULL, NULL, 2016, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Final residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a312-04f0-49b3-abf2-4daea65041cc', 'Angela Barnett', 'angela-barnett', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a312-066e-4a3a-9adc-9fb04db3f3e0', 'David Bowie has relationship with Angela Barnett', 'david-bowie-has-relationship-with-angela-barnett', 'connection', false, NULL, NULL, 1970, NULL, NULL, 1980, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a312-0cb0-427a-a350-2f09d146659f', 'Iman Abdulmajid', 'iman-abdulmajid', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a312-0e22-4af1-ac8e-ba391b5ae58a', 'David Bowie has relationship with Iman Abdulmajid', 'david-bowie-has-relationship-with-iman-abdulmajid', 'connection', false, NULL, NULL, 1992, NULL, NULL, 2016, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:06', '2025-03-26 17:43:06', NULL);
INSERT INTO public.spans VALUES ('9e86a312-f09c-4fa7-a635-c88a9ab0b7be', 'Donald Trump', 'donald-trump', 'person', false, NULL, NULL, 1946, 6, 14, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a312-f3a1-4c10-9dd8-34f41b1db58a', 'Mary Anne MacLeod', 'mary-anne-macleod', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a312-f6ed-45d4-aa20-7dbdc3d0dc58', 'Mary Anne MacLeod is family of Donald Trump', 'mary-anne-macleod-is-family-of-donald-trump', 'connection', false, NULL, NULL, 1946, 6, 14, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-0900-4beb-9b9c-996f6cf7d469', 'Fred Trump', 'fred-trump', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-0bec-4ded-b793-2a4f84b6ac8d', 'Fred Trump is family of Donald Trump', 'fred-trump-is-family-of-donald-trump', 'connection', false, NULL, NULL, 1946, 6, 14, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-196c-492e-96c5-b66759c416ea', 'Donald Trump Jr.', 'donald-trump-jr', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-1c3c-4591-b4fa-6f0d69d0faf7', 'Donald Trump is family of Donald Trump Jr.', 'donald-trump-is-family-of-donald-trump-jr', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-269b-4aab-b6ae-869547a35307', 'Ivanka Trump', 'ivanka-trump', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-28e5-4094-a223-83cbeca5920d', 'Donald Trump is family of Ivanka Trump', 'donald-trump-is-family-of-ivanka-trump', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-3306-4aeb-9432-738a5d5c8d0b', 'Eric Trump', 'eric-trump', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-3521-4e8d-b71e-9fb53889b647', 'Donald Trump is family of Eric Trump', 'donald-trump-is-family-of-eric-trump', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-3e5a-42da-a67a-f6d1c2708dee', 'Tiffany Trump', 'tiffany-trump', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-4066-4a98-a0c2-a73edd28e8b0', 'Donald Trump is family of Tiffany Trump', 'donald-trump-is-family-of-tiffany-trump', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-5a2e-44b4-9d6c-d2aaeac530f2', 'Wharton School of the University of Pennsylvania', 'wharton-school-of-the-university-of-pennsylvania', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "higher education", "course": "Economics"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-5b99-4a19-9e41-033987dd81ca', 'Donald Trump studied at Wharton School of the University of Pennsylvania', 'donald-trump-studied-at-wharton-school-of-the-university-of-pennsylvania', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1968, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "higher education", "course": "Economics", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-630c-4659-8fa9-2e09844861d3', 'Donald Trump worked at United States Government', 'donald-trump-worked-at-united-states-government', 'connection', false, NULL, NULL, 2017, NULL, NULL, 2021, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "President of the United States", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-68bb-4af4-8e01-3c2b95e3836b', 'The Trump Organization', 'the-trump-organization', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Businessman and television personality"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-6a17-4cba-ba90-895e3d3eeaf1', 'Donald Trump worked at The Trump Organization', 'donald-trump-worked-at-the-trump-organization', 'connection', false, NULL, NULL, 1971, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Businessman and television personality", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-7020-4cac-9261-2b03b74e26b0', 'Queens, New York, USA', 'queens-new-york-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-7175-4086-b676-08ee928d8284', 'Donald Trump lived in Queens, New York, USA', 'donald-trump-lived-in-queens-new-york-usa', 'connection', false, NULL, NULL, 1946, NULL, NULL, 1968, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-774d-4837-9fa3-653a0ae95fe0', 'Manhattan, New York, USA', 'manhattan-new-york-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-789b-4f53-95bc-2a82a96e6780', 'Donald Trump lived in Manhattan, New York, USA', 'donald-trump-lived-in-manhattan-new-york-usa', 'connection', false, NULL, NULL, 1971, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-7e96-4f36-b0a3-c7afff537fd2', 'Ivana Trump', 'ivana-trump', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-7fe6-445b-948c-0501f8ca9d3a', 'Donald Trump has relationship with Ivana Trump', 'donald-trump-has-relationship-with-ivana-trump', 'connection', false, NULL, NULL, 1977, NULL, NULL, 1992, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-85d8-4130-be66-39526648b1a3', 'Marla Maples', 'marla-maples', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-8725-48bc-90f4-9c7638a3d369', 'Donald Trump has relationship with Marla Maples', 'donald-trump-has-relationship-with-marla-maples', 'connection', false, NULL, NULL, 1993, NULL, NULL, 1999, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-8cd2-4a42-b2a0-3a1959401491', 'Melania Trump', 'melania-trump', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a313-8e24-46a7-ad2a-dafddc616f3e', 'Donald Trump has relationship with Melania Trump', 'donald-trump-has-relationship-with-melania-trump', 'connection', false, NULL, NULL, 2005, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:07', '2025-03-26 17:43:07', NULL);
INSERT INTO public.spans VALUES ('9e86a302-0a71-4859-8a1b-32fffff948bf', 'Ali Northover is family of Garvey Northover', 'ali-northover-is-family-of-garvey-northover', 'connection', false, NULL, NULL, 2011, 7, 14, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:56', '2025-03-26 17:43:08', NULL);
INSERT INTO public.spans VALUES ('9e86a316-4e33-433d-8ffd-5779d0451f8a', 'George Harrison', 'george-harrison', 'person', false, NULL, NULL, 1943, 2, 25, 2001, 11, 29, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-51c9-412d-8562-2f40af154fd2', 'Louise French Harrison', 'louise-french-harrison', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-553c-48c7-997a-6773ac0be4de', 'Louise French Harrison is family of George Harrison', 'louise-french-harrison-is-family-of-george-harrison', 'connection', false, NULL, NULL, 1943, 2, 25, 2001, 11, 29, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-6e86-4727-9801-ccd7a5a1021d', 'Harold Hargreaves Harrison', 'harold-hargreaves-harrison', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-71f1-461b-a0d3-0263038a8d44', 'Harold Hargreaves Harrison is family of George Harrison', 'harold-hargreaves-harrison-is-family-of-george-harrison', 'connection', false, NULL, NULL, 1943, 2, 25, 2001, 11, 29, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-83ee-4c1d-bb66-decfc3d209c2', 'Dhani Harrison', 'dhani-harrison', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a315-6045-4320-83b3-b40f42e09013', 'Simon Northover', 'simon-northover', 'person', false, NULL, NULL, 1978, 8, 15, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:08', '2025-03-26 17:43:45', NULL);
INSERT INTO public.spans VALUES ('9e86a315-6512-4d8f-819f-0fc7f0f6670f', 'Simon Northover is family of Garvey Northover', 'simon-northover-is-family-of-garvey-northover', 'connection', false, NULL, NULL, 2011, 7, 14, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:08', '2025-03-26 17:43:45', NULL);
INSERT INTO public.spans VALUES ('9e86a316-b049-472c-9160-f655678dddef', 'George Harrison worked at Self-Employed', 'george-harrison-worked-at-self-employed', 'connection', false, NULL, NULL, 1970, NULL, NULL, 2001, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Solo Artist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-b8ee-46be-bda8-126d9140c341', 'Liverpool, England', 'liverpool-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-baf6-4aef-a335-755d7e41accf', 'George Harrison lived in Liverpool, England', 'george-harrison-lived-in-liverpool-england', 'connection', false, NULL, NULL, 1943, NULL, NULL, 1960, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-c302-4d1d-8a67-ac0a992fe4de', 'Los Angeles, USA', 'los-angeles-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-c4ed-42f7-88e6-e10a12644041', 'George Harrison lived in Los Angeles, USA', 'george-harrison-lived-in-los-angeles-usa', 'connection', false, NULL, NULL, 1970, NULL, NULL, 2001, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-cc90-4712-a4e1-0f30c4cae453', 'Pattie Boyd', 'pattie-boyd', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-ce55-4bf4-bfec-f230b26bfba6', 'George Harrison has relationship with Pattie Boyd', 'george-harrison-has-relationship-with-pattie-boyd', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1977, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-d586-40e4-b1f6-8cde3ee4162f', 'Olivia Trinidad Arias', 'olivia-trinidad-arias', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a316-d729-46f0-a801-314d5bb827a7', 'George Harrison has relationship with Olivia Trinidad Arias', 'george-harrison-has-relationship-with-olivia-trinidad-arias', 'connection', false, NULL, NULL, 1978, NULL, NULL, 2001, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:09', '2025-03-26 17:43:09', NULL);
INSERT INTO public.spans VALUES ('9e86a317-afe8-4d0f-ac79-24e20d44a019', 'George Orwell', 'george-orwell', 'person', false, NULL, NULL, 1903, 6, 25, 1950, 1, 21, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-b1c7-4273-9a13-3ee211b159f1', 'Ida Blair', 'ida-blair', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-b3f9-408d-8f59-71f7a61c8142', 'Ida Blair is family of George Orwell', 'ida-blair-is-family-of-george-orwell', 'connection', false, NULL, NULL, 1903, 6, 25, 1950, 1, 21, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-c30e-4d5b-8277-0e87b77668be', 'Richard Walmesley Blair', 'richard-walmesley-blair', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-c49c-4eb4-a88a-dacc19492a84', 'Richard Walmesley Blair is family of George Orwell', 'richard-walmesley-blair-is-family-of-george-orwell', 'connection', false, NULL, NULL, 1903, 6, 25, 1950, 1, 21, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-d1eb-4234-9889-018c05cf4006', 'St Cyprian''s School', 'st-cyprians-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-d40c-4ba8-a0d3-b6a3b73d801c', 'George Orwell studied at St Cyprian''s School', 'george-orwell-studied-at-st-cyprians-school', 'connection', false, NULL, NULL, 1911, NULL, NULL, 1916, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-da26-4d16-a2c4-e6b2b5c5b0c1', 'Eton College', 'eton-college', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-db4b-4d94-b0eb-19105c627beb', 'George Orwell studied at Eton College', 'george-orwell-studied-at-eton-college', 'connection', false, NULL, NULL, 1917, NULL, NULL, 1921, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-dfea-42b1-ae4f-2820b989ffb2', 'Indian Imperial Police', 'indian-imperial-police', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Imperial Policeman"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-e0f6-42e9-8fa1-7debb4ccaf29', 'George Orwell worked at Indian Imperial Police', 'george-orwell-worked-at-indian-imperial-police', 'connection', false, NULL, NULL, 1922, NULL, NULL, 1927, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Imperial Policeman", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-e5b8-4208-92fb-dcb53456ccc6', 'Freelance', 'freelance', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Writer"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-e6d6-45d7-9c28-2e8bd8777269', 'George Orwell worked at Freelance', 'george-orwell-worked-at-freelance', 'connection', false, NULL, NULL, 1927, NULL, NULL, 1950, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Writer", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-eb86-45dd-8afb-cd3f9ec46279', 'Motihari, Bihar, India', 'motihari-bihar-india', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-ec9c-4947-adbe-d12fbccb8b1e', 'George Orwell lived in Motihari, Bihar, India', 'george-orwell-lived-in-motihari-bihar-india', 'connection', false, NULL, NULL, 1903, NULL, NULL, 1904, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-f178-4217-a571-17ed19f15cd4', 'England', 'england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-f287-4b0e-a694-ba71de15943c', 'George Orwell lived in England', 'george-orwell-lived-in-england', 'connection', false, NULL, NULL, 1904, NULL, NULL, 1950, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-f76f-461e-95a2-40c5b3d511bd', 'Eileen O''Shaughnessy', 'eileen-oshaughnessy', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a317-f891-40aa-a07b-d5f19805b7c4', 'George Orwell has relationship with Eileen O''Shaughnessy', 'george-orwell-has-relationship-with-eileen-oshaughnessy', 'connection', false, NULL, NULL, 1936, NULL, NULL, 1945, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a318-0777-49dc-832b-12703413c44e', 'Sonia Brownell', 'sonia-brownell', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a318-08ba-4c02-b3a7-daa72a2ace30', 'George Orwell has relationship with Sonia Brownell', 'george-orwell-has-relationship-with-sonia-brownell', 'connection', false, NULL, NULL, 1949, NULL, NULL, 1950, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:10', '2025-03-26 17:43:10', NULL);
INSERT INTO public.spans VALUES ('9e86a318-f04a-4bbf-b6fd-8eec9634e544', 'Gerald Scott', 'gerald-scott', 'person', false, NULL, NULL, 1917, 12, 13, 1993, 5, 19, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:11', '2025-03-26 17:43:11', NULL);
INSERT INTO public.spans VALUES ('9e86a31d-7aad-45b8-b6cb-c48b6c48e944', 'Sophie Northover', 'sophie-northover', 'person', false, NULL, NULL, 1992, 11, 7, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:14', '2025-03-26 17:43:45', NULL);
INSERT INTO public.spans VALUES ('9e86a319-092c-45e2-896e-a6bc1d6dbbc9', 'John Scott', 'john-scott', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:11', '2025-03-26 17:43:11', NULL);
INSERT INTO public.spans VALUES ('9e86a319-0c9c-4d4a-956b-0d43fa6b16d9', 'Gerald Scott is family of John Scott', 'gerald-scott-is-family-of-john-scott', 'connection', false, NULL, NULL, NULL, NULL, NULL, 1993, 5, 19, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:11', '2025-03-26 17:43:11', NULL);
INSERT INTO public.spans VALUES ('9e86a31a-cae1-4264-b731-af3e998d6239', 'Guest User', 'guest-user', 'person', false, NULL, NULL, 2008, 1, 3, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:12', '2025-03-26 17:43:12', NULL);
INSERT INTO public.spans VALUES ('9e86a31a-cea7-4029-98ec-271812eaaa9e', 'Lifespan', 'lifespan', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Tester"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:12', '2025-03-26 17:43:12', NULL);
INSERT INTO public.spans VALUES ('9e86a31a-d284-40a8-86cb-68013b926a1b', 'Guest User worked at Lifespan', 'guest-user-worked-at-lifespan', 'connection', false, NULL, NULL, 2025, 1, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"role": "Tester", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:12', '2025-03-26 17:43:12', NULL);
INSERT INTO public.spans VALUES ('9e86a31c-9b7b-422b-b53b-8eb49a4d03bf', 'Gustave Scott', 'gustave-scott', 'person', false, NULL, NULL, 1886, 6, 1, 1967, 1, 1, 'day', 'day', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:13', '2025-03-26 17:43:13', NULL);
INSERT INTO public.spans VALUES ('9e86a31d-76c3-476d-b2e2-8bc9f01232bc', 'Indiana Northover', 'indiana-northover', 'person', false, NULL, NULL, 2023, 12, 27, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:14', '2025-03-26 17:43:14', NULL);
INSERT INTO public.spans VALUES ('9e86a320-7777-4470-862f-acf279ec3b93', 'Sheila McInnes', 'sheila-mcinnes', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a31f-6eb7-4799-90c4-9dc1bad51cfd', 'River Northover', 'river-northover', 'person', false, NULL, NULL, 2019, 10, 28, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:15', '2025-03-26 17:43:42', NULL);
INSERT INTO public.spans VALUES ('9e86a30c-9fd3-4974-8734-951062222661', 'Chris Northover is family of Jack Northover', 'chris-northover-is-family-of-jack-northover', 'connection', false, NULL, NULL, 1982, 11, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:03', '2025-03-26 17:43:15', NULL);
INSERT INTO public.spans VALUES ('9e86a318-f3a8-4eab-a2d9-9d99d3b5b0fd', 'Sheila Northover', 'sheila-northover', 'person', false, NULL, NULL, 1947, 5, 13, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:11', '2025-03-26 17:43:44', NULL);
INSERT INTO public.spans VALUES ('9e86a31d-962f-4df0-be17-204073ae9701', 'Jack Northover is family of Indiana Northover', 'jack-northover-is-family-of-indiana-northover', 'connection', false, NULL, NULL, 2023, 12, 27, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:14', '2025-03-26 17:43:15', NULL);
INSERT INTO public.spans VALUES ('9e86a320-7b9d-405b-86ad-a381f9fd06a0', 'Sheila McInnes is family of Jenny McInnes', 'sheila-mcinnes-is-family-of-jenny-mcinnes', 'connection', false, NULL, NULL, 1980, 6, 6, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a320-87b2-4b39-8238-2a987744136e', 'Ian McInnes', 'ian-mcinnes', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a320-8a17-4c5a-8967-0a4acd0a17e9', 'Ian McInnes is family of Jenny McInnes', 'ian-mcinnes-is-family-of-jenny-mcinnes', 'connection', false, NULL, NULL, 1980, 6, 6, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-7306-4937-b66d-4b45f6f2ad0d', 'Jimi Hendrix', 'jimi-hendrix', 'person', false, NULL, NULL, 1942, 11, 27, 1970, 9, 18, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-75ba-4b57-a2f7-760feaed8279', 'Lucille Jeter', 'lucille-jeter', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a31f-622a-41fa-951e-ba6b324e8bab', 'Katy Northover is family of Jack Northover', 'katy-northover-is-family-of-jack-northover', 'connection', false, NULL, NULL, 1982, 11, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:15', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a318-f5fd-497e-a856-d4a963c0f597', 'Gerald Scott is family of Sheila Northover', 'gerald-scott-is-family-of-sheila-northover', 'connection', false, NULL, NULL, 1947, 5, 13, 1993, 5, 19, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:11', '2025-03-26 17:43:44', NULL);
INSERT INTO public.spans VALUES ('9e86a31d-7ebc-4979-8787-bf24cc0c199e', 'Sophie Northover is family of Indiana Northover', 'sophie-northover-is-family-of-indiana-northover', 'connection', false, NULL, NULL, 2023, 12, 27, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:14', '2025-03-26 17:43:45', NULL);
INSERT INTO public.spans VALUES ('9e86a321-77b0-4071-9cff-dd51ed0e1dac', 'Lucille Jeter is family of Jimi Hendrix', 'lucille-jeter-is-family-of-jimi-hendrix', 'connection', false, NULL, NULL, 1942, 11, 27, 1970, 9, 18, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-8d42-4480-a07f-d4b98070f73a', 'James Allen Hendrix', 'james-allen-hendrix', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-91ac-4a22-a277-31939c88774d', 'James Allen Hendrix is family of Jimi Hendrix', 'james-allen-hendrix-is-family-of-jimi-hendrix', 'connection', false, NULL, NULL, 1942, 11, 27, 1970, 9, 18, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-9f12-4384-89bd-15a14126ac8e', 'Garfield High School', 'garfield-high-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-a112-4197-8644-0bd9adb864dd', 'Jimi Hendrix studied at Garfield High School', 'jimi-hendrix-studied-at-garfield-high-school', 'connection', false, NULL, NULL, 1958, NULL, NULL, 1961, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-a872-4fd7-afbc-0286865dcd0b', 'The Jimi Hendrix Experience', 'the-jimi-hendrix-experience', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Guitarist in The Jimi Hendrix Experience"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-a9aa-415f-92d0-b7e98dc81acd', 'Jimi Hendrix worked at The Jimi Hendrix Experience', 'jimi-hendrix-worked-at-the-jimi-hendrix-experience', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1969, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Guitarist in The Jimi Hendrix Experience", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:16', '2025-03-26 17:43:16', NULL);
INSERT INTO public.spans VALUES ('9e86a321-af7b-4b3e-beb7-b5923841b052', 'Band of Gypsys', 'band-of-gypsys', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Guitarist in Band of Gypsys"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a321-b116-45c8-b658-8c6d7359f784', 'Jimi Hendrix worked at Band of Gypsys', 'jimi-hendrix-worked-at-band-of-gypsys', 'connection', false, NULL, NULL, 1969, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Guitarist in Band of Gypsys", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a321-b861-45ff-bb21-47359683d1a0', 'Seattle, Washington, USA', 'seattle-washington-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a321-b9e9-4922-93a8-c49fcfa1c3b5', 'Jimi Hendrix lived in Seattle, Washington, USA', 'jimi-hendrix-lived-in-seattle-washington-usa', 'connection', false, NULL, NULL, 1942, 11, 27, 1961, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a321-c1e2-46e1-9c1b-668c02a5684b', 'London, UK', 'london-uk', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a321-c328-47f5-bed7-30e89dcad2f0', 'Jimi Hendrix lived in London, UK', 'jimi-hendrix-lived-in-london-uk', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1969, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-a07f-405f-832a-b42d8cea8f96', 'Joan Scott', 'joan-scott', 'person', false, NULL, NULL, 1921, 4, 13, 2007, 4, 11, 'day', 'day', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-a2f7-4a04-bddf-92b3744d7043', 'Winifred Garvie', 'winifred-garvie', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-a4a8-4627-bfd6-26162172b335', 'Winifred Garvie is family of Joan Scott', 'winifred-garvie-is-family-of-joan-scott', 'connection', false, NULL, NULL, 1921, 4, 13, 2007, 4, 11, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-b51a-4314-81e1-39d6c73afc53', 'John George Garvie', 'john-george-garvie', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-b7f9-4949-bbbf-ccaa4fb2fc33', 'John George Garvie is family of Joan Scott', 'john-george-garvie-is-family-of-joan-scott', 'connection', false, NULL, NULL, 1921, 4, 13, 2007, 4, 11, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-ce0a-4b60-a988-8bbbd2af62b6', 'Joan Scott is family of John Scott', 'joan-scott-is-family-of-john-scott', 'connection', false, NULL, NULL, NULL, NULL, NULL, 2007, 4, 11, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-d3f1-43f3-842c-39de2a47d2fb', 'Local Primary School', 'local-primary-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-c49d-439d-8541-7fdfb9bfc71d', 'Joan Scott is family of Sheila Northover', 'joan-scott-is-family-of-sheila-northover', 'connection', false, NULL, NULL, 1947, 5, 13, 2007, 4, 11, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:44', NULL);
INSERT INTO public.spans VALUES ('9e86a322-d50f-4840-87cc-84ea0a340348', 'Joan Scott studied at Local Primary School', 'joan-scott-studied-at-local-primary-school', 'connection', false, NULL, NULL, 1929, 9, NULL, 1930, 6, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-db27-4bd9-8a08-db4a1d05ffd0', 'St Winifreds', 'st-winifreds', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-dc75-4726-80a0-94b13c68e15e', 'Joan Scott studied at St Winifreds', 'joan-scott-studied-at-st-winifreds', 'connection', false, NULL, NULL, 1930, 9, 1, 1931, 6, 1, 'day', 'day', 'placeholder', NULL, NULL, '{"level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-e223-4f50-9aae-59145ecb28b9', 'High School', 'high-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-e35f-4de7-8b30-6ff67f666c9b', 'Joan Scott studied at High School', 'joan-scott-studied-at-high-school', 'connection', false, NULL, NULL, 1931, 9, 1, 1932, 6, 1, 'day', 'day', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-e8f3-4bfe-8973-ba31a66ba7fb', 'English School in Cairo', 'english-school-in-cairo', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-ea25-4fb0-9d00-9f52b6fcc818', 'Joan Scott studied at English School in Cairo', 'joan-scott-studied-at-english-school-in-cairo', 'connection', false, NULL, NULL, 1932, 9, 1, 1938, 6, 1, 'day', 'day', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-ef8e-44af-813e-31f21247cdd2', 'Sunderland', 'sunderland', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "childhood"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-f0e4-4601-9b84-707764619eff', 'Joan Scott lived in Sunderland', 'joan-scott-lived-in-sunderland', 'connection', false, NULL, NULL, 1921, 4, 13, 1926, 2, 1, 'day', 'day', 'placeholder', NULL, NULL, '{"reason": "childhood", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-f66d-4a1a-875e-2a6f5737956a', 'Hurgharda', 'hurgharda', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "father''s work on the oil field"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-475f-4568-bf5c-091c3ca8ef3c', 'Keir Starmer', 'keir-starmer', 'person', false, NULL, NULL, 1962, 9, 2, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a322-f7b2-4ece-b305-df8fd9e94333', 'Joan Scott lived in Hurgharda', 'joan-scott-lived-in-hurgharda', 'connection', false, NULL, NULL, 1926, 2, 1, 1932, 9, 1, 'day', 'day', 'placeholder', NULL, NULL, '{"reason": "father''s work on the oil field", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-fd5f-4914-8e7c-6db0a9470e8b', 'Cairo', 'cairo', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "schooling"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a322-feb8-4c9d-a7d9-6a6907a52dba', 'Joan Scott lived in Cairo', 'joan-scott-lived-in-cairo', 'connection', false, NULL, NULL, 1932, 9, 1, 1938, 6, 1, 'day', 'day', 'placeholder', NULL, NULL, '{"reason": "schooling", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a323-04bd-45ff-accd-91f9abfca045', 'Joan Scott has relationship with Gerald Scott', 'joan-scott-has-relationship-with-gerald-scott', 'connection', false, NULL, NULL, 1945, 1, 1, 2007, 4, 11, 'day', 'day', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:17', '2025-03-26 17:43:17', NULL);
INSERT INTO public.spans VALUES ('9e86a323-e2d6-4496-8564-9da166be8e79', 'Joe Biden', 'joe-biden', 'person', false, NULL, NULL, 1942, 11, 20, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a323-e539-44c4-a4ec-7c7cda3db11e', 'St. Paul''s Elementary School', 'st-pauls-elementary-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a323-e760-40bc-a3c3-addbee27b26f', 'Joe Biden studied at St. Paul''s Elementary School', 'joe-biden-studied-at-st-pauls-elementary-school', 'connection', false, NULL, NULL, 1946, NULL, NULL, 1950, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a323-f8f9-40e9-9352-6f09003eae37', 'Archmere Academy', 'archmere-academy', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a323-fbb2-41e0-81db-f42a8b60571d', 'Joe Biden studied at Archmere Academy', 'joe-biden-studied-at-archmere-academy', 'connection', false, NULL, NULL, 1950, NULL, NULL, 1959, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-086d-455d-a849-e0b0b5ddb86f', 'University of Delaware', 'university-of-delaware', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "undergraduate", "course": "Bachelor of Arts, History and Political Science"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-0a8d-4602-a41a-549797278f8c', 'Joe Biden studied at University of Delaware', 'joe-biden-studied-at-university-of-delaware', 'connection', false, NULL, NULL, 1961, NULL, NULL, 1965, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "undergraduate", "course": "Bachelor of Arts, History and Political Science", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-13a5-4364-9f37-d2396f811139', 'Syracuse University College of Law', 'syracuse-university-college-of-law', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "postgraduate", "course": "Juris Doctor (JD)"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-1519-42a3-a12f-81be4b68059c', 'Joe Biden studied at Syracuse University College of Law', 'joe-biden-studied-at-syracuse-university-college-of-law', 'connection', false, NULL, NULL, 1965, NULL, NULL, 1968, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "postgraduate", "course": "Juris Doctor (JD)", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-1d42-4f0f-b897-9af32c50106e', 'Private Practice', 'private-practice', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Attorney", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-1f1b-43ac-ad88-ccbb58245cd2', 'Joe Biden worked at Private Practice', 'joe-biden-worked-at-private-practice', 'connection', false, NULL, NULL, 1969, NULL, NULL, 1972, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Attorney", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-274c-4eb9-bf8b-5b32406cbf37', 'New Castle County, Delaware', 'new-castle-county-delaware', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "New Castle County Council Member", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-28b3-4213-9f31-5639cad2d8a5', 'Joe Biden worked at New Castle County, Delaware', 'joe-biden-worked-at-new-castle-county-delaware', 'connection', false, NULL, NULL, 1970, NULL, NULL, 1972, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "New Castle County Council Member", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-30e5-4147-a7dd-7d9f999d8266', 'Delaware', 'delaware', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "U.S. Senator", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-3269-4ab0-8df4-90c1326db69e', 'Joe Biden worked at Delaware', 'joe-biden-worked-at-delaware', 'connection', false, NULL, NULL, 1973, NULL, NULL, 2009, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "U.S. Senator", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-3a80-40f3-938d-385e6072a986', 'United States of America', 'united-states-of-america', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Vice President", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a325-4c22-41e0-b9cc-52fb8dd1d108', 'Joe Northover', 'joe-northover', 'person', false, NULL, NULL, 1990, 11, 9, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:19', '2025-03-26 17:43:19', NULL);
INSERT INTO public.spans VALUES ('9e86a324-3bf5-49a6-998f-e87fbb22eed2', 'Joe Biden worked at United States of America', 'joe-biden-worked-at-united-states-of-america', 'connection', false, NULL, NULL, 2021, NULL, NULL, 2017, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "President", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-4669-426d-95e4-bed3694e14dd', 'Scranton, Pennsylvania, USA', 'scranton-pennsylvania-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-47d8-4bfb-accf-1f41d007f670', 'Joe Biden lived in Scranton, Pennsylvania, USA', 'joe-biden-lived-in-scranton-pennsylvania-usa', 'connection', false, NULL, NULL, 1942, NULL, NULL, 1953, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-4f9a-4018-a267-6c752da21380', 'Claymont, Delaware, USA', 'claymont-delaware-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-511d-4cf7-afa3-dcf2ad4b5086', 'Joe Biden lived in Claymont, Delaware, USA', 'joe-biden-lived-in-claymont-delaware-usa', 'connection', false, NULL, NULL, 1953, NULL, NULL, 1955, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-5859-4d34-8737-48a12f2fc39c', 'Greenville, Delaware, USA', 'greenville-delaware-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-59b1-49ad-a88b-5e4859252837', 'Joe Biden lived in Greenville, Delaware, USA', 'joe-biden-lived-in-greenville-delaware-usa', 'connection', false, NULL, NULL, 1955, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-60a8-4731-90f5-97a1d7b8bca0', 'Neilia Hunter', 'neilia-hunter', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-6208-4caf-a13a-122c133601d5', 'Joe Biden has relationship with Neilia Hunter', 'joe-biden-has-relationship-with-neilia-hunter', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1972, 12, 18, 'year', 'day', 'placeholder', NULL, NULL, '{"type": "relationship", "connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-68cf-4d01-a36c-4569f0ddbd6a', 'Jill Biden', 'jill-biden', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a324-6a48-48dc-95ad-a3b4108d2737', 'Joe Biden has relationship with Jill Biden', 'joe-biden-has-relationship-with-jill-biden', 'connection', false, NULL, NULL, 1977, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship", "connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:18', '2025-03-26 17:43:18', NULL);
INSERT INTO public.spans VALUES ('9e86a325-4f73-4533-97f5-13d827a91f81', 'Lindsay Northover', 'lindsay-northover', 'person', false, NULL, NULL, 1954, 8, 20, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:19', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a326-580e-4718-bf84-2342e6dda3b0', 'John Lennon', 'john-lennon', 'person', false, NULL, NULL, 1940, 10, 9, 1980, 12, 8, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-5bce-4164-ad09-1dabcd561cca', 'Julia Lennon', 'julia-lennon', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-5f95-418a-a5a1-a062b051a984', 'Julia Lennon is family of John Lennon', 'julia-lennon-is-family-of-john-lennon', 'connection', false, NULL, NULL, 1940, 10, 9, 1980, 12, 8, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-76f8-43e4-bfa4-95b68b864bfc', 'Alfred Lennon', 'alfred-lennon', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a325-6add-4436-8489-13870be94e31', 'John Northover', 'john-northover', 'person', false, NULL, NULL, 1947, 6, 16, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:19', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a325-6e8f-4bd5-81c3-93900750ccee', 'John Northover is family of Joe Northover', 'john-northover-is-family-of-joe-northover', 'connection', false, NULL, NULL, 1990, 11, 9, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:19', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a325-52ed-4c2c-877a-9091dcc85c30', 'Lindsay Northover is family of Joe Northover', 'lindsay-northover-is-family-of-joe-northover', 'connection', false, NULL, NULL, 1990, 11, 9, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:19', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a326-7a1a-4f77-b3c4-57930dbec868', 'Alfred Lennon is family of John Lennon', 'alfred-lennon-is-family-of-john-lennon', 'connection', false, NULL, NULL, 1940, 10, 9, 1980, 12, 8, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-9841-4d62-bfc5-f366297d5431', 'Julian Lennon', 'julian-lennon', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-9c22-43ed-aed0-b517faeb356a', 'John Lennon is family of Julian Lennon', 'john-lennon-is-family-of-julian-lennon', 'connection', false, NULL, NULL, NULL, NULL, NULL, 1980, 12, 8, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-ad43-473e-bff0-ecce4bd96fb2', 'Sean Lennon', 'sean-lennon', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-afd5-4ac1-82c4-ca022d6eced5', 'John Lennon is family of Sean Lennon', 'john-lennon-is-family-of-sean-lennon', 'connection', false, NULL, NULL, NULL, NULL, NULL, 1980, 12, 8, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-bd46-41e3-ba3e-1e4c4818b897', 'Dovedale Primary School', 'dovedale-primary-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-bed3-48a8-a912-ffeaea3d0fd2', 'John Lennon studied at Dovedale Primary School', 'john-lennon-studied-at-dovedale-primary-school', 'connection', false, NULL, NULL, 1946, NULL, NULL, 1952, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-c636-4c78-b8cf-53a01b724973', 'Quarry Bank High School', 'quarry-bank-high-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-c773-4261-a0eb-22bd27dbc200', 'John Lennon studied at Quarry Bank High School', 'john-lennon-studied-at-quarry-bank-high-school', 'connection', false, NULL, NULL, 1952, NULL, NULL, 1957, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-cf85-427c-a25f-6aca150aaee2', 'Liverpool College of Art', 'liverpool-college-of-art', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "higher education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-d0c1-4b66-8b33-fae6b17c1623', 'John Lennon studied at Liverpool College of Art', 'john-lennon-studied-at-liverpool-college-of-art', 'connection', false, NULL, NULL, 1957, NULL, NULL, 1960, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "higher education", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-d94d-4e8e-a840-75700a36a88e', 'John Lennon worked at The Beatles', 'john-lennon-worked-at-the-beatles', 'connection', false, NULL, NULL, 1960, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Musician, Singer, Songwriter", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-e127-4323-acdf-c0ad50c4e967', 'John Lennon worked at Self-Employed', 'john-lennon-worked-at-self-employed', 'connection', false, NULL, NULL, 1970, NULL, NULL, 1980, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Solo Artist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-e87e-4b8d-be32-e8030e27c954', 'Liverpool, England, United Kingdom', 'liverpool-england-united-kingdom', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-e9e6-4758-b0f3-d46f68ab97a6', 'John Lennon lived in Liverpool, England, United Kingdom', 'john-lennon-lived-in-liverpool-england-united-kingdom', 'connection', false, NULL, NULL, 1940, 10, 9, 1963, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-f145-48cb-9010-92c7b5cf8fec', 'John Lennon lived in New York City, New York, USA', 'john-lennon-lived-in-new-york-city-new-york-usa', 'connection', false, NULL, NULL, 1971, NULL, NULL, 1980, 12, 8, 'year', 'day', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-f7d5-4fa1-8bf4-8edf497aa744', 'Cynthia Powell', 'cynthia-powell', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a329-731f-4478-a77e-ab86b60dc0f1', 'Saskatoon, Saskatchewan, Canada', 'saskatoon-saskatchewan-canada', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-74c1-4825-905e-4dc6a959bb5e', 'Joni Mitchell lived in Saskatoon, Saskatchewan, Canada', 'joni-mitchell-lived-in-saskatoon-saskatchewan-canada', 'connection', false, NULL, NULL, 1943, 11, 7, 1965, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-7ca4-4844-b0e7-bfb6303db915', 'Toronto, Ontario, Canada', 'toronto-ontario-canada', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-7e0e-4a7c-a8fe-a7006d524022', 'Joni Mitchell lived in Toronto, Ontario, Canada', 'joni-mitchell-lived-in-toronto-ontario-canada', 'connection', false, NULL, NULL, 1965, NULL, NULL, 1968, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-8572-43f6-9907-f8126d427504', 'Los Angeles, California, United States', 'los-angeles-california-united-states', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-86e9-4f8d-9480-e9618ad377e4', 'Joni Mitchell lived in Los Angeles, California, United States', 'joni-mitchell-lived-in-los-angeles-california-united-states', 'connection', false, NULL, NULL, 1968, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-8e70-452a-8981-1448a7a55e3b', 'Chuck Mitchell', 'chuck-mitchell', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-8fd9-42fc-b973-0f7a9f354313', 'Joni Mitchell has relationship with Chuck Mitchell', 'joni-mitchell-has-relationship-with-chuck-mitchell', 'connection', false, NULL, NULL, 1965, NULL, NULL, 1967, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-968e-4605-9c0d-1697ab0fc442', 'Graham Nash', 'graham-nash', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a326-f92f-4eb0-99db-eb50fd96a6b9', 'John Lennon has relationship with Cynthia Powell', 'john-lennon-has-relationship-with-cynthia-powell', 'connection', false, NULL, NULL, 1962, 8, 23, 1968, 11, 8, 'day', 'day', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a326-ff82-4ea5-b9d1-4992fdbce034', 'Yoko Ono', 'yoko-ono', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a327-00c3-43b0-a24f-2d1d457c1f10', 'John Lennon has relationship with Yoko Ono', 'john-lennon-has-relationship-with-yoko-ono', 'connection', false, NULL, NULL, 1969, 3, 20, 1980, 12, 8, 'day', 'day', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:20', '2025-03-26 17:43:20', NULL);
INSERT INTO public.spans VALUES ('9e86a328-1b65-464d-b49e-c9d93b9f71c7', 'John Northover is family of Simon Northover', 'john-northover-is-family-of-simon-northover', 'connection', false, NULL, NULL, 1978, 8, 15, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:45', NULL);
INSERT INTO public.spans VALUES ('9e86a327-f102-481d-8982-4aaf99a77842', 'Peggy Northover is family of John Northover', 'peggy-northover-is-family-of-john-northover', 'connection', false, NULL, NULL, 1947, 6, 16, 2014, 7, 15, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a329-1219-41b2-bff8-797e7fba52c3', 'Joni Mitchell', 'joni-mitchell', 'person', false, NULL, NULL, 1943, 11, 7, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a329-155c-4ea4-8148-0af80c776db6', 'Myrtle Marguerite McKee', 'myrtle-marguerite-mckee', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a329-1901-4cd8-a227-565ce0c1c391', 'Myrtle Marguerite McKee is family of Joni Mitchell', 'myrtle-marguerite-mckee-is-family-of-joni-mitchell', 'connection', false, NULL, NULL, 1943, 11, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a329-2fe0-4aa5-addd-fe9e969223cb', 'William Andrew Anderson', 'william-andrew-anderson', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a329-3348-4700-892e-de2497ad3c9b', 'William Andrew Anderson is family of Joni Mitchell', 'william-andrew-anderson-is-family-of-joni-mitchell', 'connection', false, NULL, NULL, 1943, 11, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a329-41fb-4773-99bb-b9beac591389', 'Kelly Dale Anderson', 'kelly-dale-anderson', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a329-4402-4d41-8fc2-4bc8eba799dc', 'Joni Mitchell is family of Kelly Dale Anderson', 'joni-mitchell-is-family-of-kelly-dale-anderson', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a329-4fdf-46e8-b8d8-9480957ae319', 'Aden Bowman Collegiate', 'aden-bowman-collegiate', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:21', NULL);
INSERT INTO public.spans VALUES ('9e86a329-520e-4762-9939-ec2081b149f6', 'Joni Mitchell studied at Aden Bowman Collegiate', 'joni-mitchell-studied-at-aden-bowman-collegiate', 'connection', false, NULL, NULL, 1957, NULL, NULL, 1960, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-5c88-4f14-85e3-b152e69549d4', 'Alberta College of Art and Design', 'alberta-college-of-art-and-design', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "art"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-5e46-4fe8-9e9f-d027e5cab58a', 'Joni Mitchell studied at Alberta College of Art and Design', 'joni-mitchell-studied-at-alberta-college-of-art-and-design', 'connection', false, NULL, NULL, 1963, NULL, NULL, 1964, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "art", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-67af-47f8-a9b8-6783ee61c77d', 'Self-employed', 'self-employed-2', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Musician"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a329-6936-4fa2-be55-880cdf684a2d', 'Joni Mitchell worked at Self-employed', 'joni-mitchell-worked-at-self-employed', 'connection', false, NULL, NULL, 1967, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Painter", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a328-2f69-451d-a919-daee35ea8161', 'John Northover is family of Louisa Denison', 'john-northover-is-family-of-louisa-denison', 'connection', false, NULL, NULL, 1993, 3, 28, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:29', NULL);
INSERT INTO public.spans VALUES ('9e86a328-0fde-4ea0-95ae-b94384460611', 'John Northover is family of Richard Northover', 'john-northover-is-family-of-richard-northover', 'connection', false, NULL, NULL, 1976, 2, 13, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a328-251d-4063-a3af-ada17eb219ba', 'John Northover is family of Tom Northover', 'john-northover-is-family-of-tom-northover', 'connection', false, NULL, NULL, 1989, 1, 23, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a329-97db-4251-ad77-b30faf914a2a', 'Joni Mitchell has relationship with Graham Nash', 'joni-mitchell-has-relationship-with-graham-nash', 'connection', false, NULL, NULL, 1968, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:22', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a32b-6cc7-4c95-91e3-cfbbf0362f46', 'Justin Vernon', 'justin-vernon', 'person', false, NULL, NULL, 1981, 4, 30, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:23', '2025-03-26 17:43:23', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-4ae8-4da7-a7da-718a65644942', 'Josephine Starmer', 'josephine-starmer', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-4eee-4ecf-be4c-b816ef086adb', 'Josephine Starmer is family of Keir Starmer', 'josephine-starmer-is-family-of-keir-starmer', 'connection', false, NULL, NULL, 1962, 9, 2, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-6761-4ad1-8655-c5181e2e4cb8', 'Rodney Starmer', 'rodney-starmer', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-6ac6-44ca-8162-7c2e6736e6a0', 'Rodney Starmer is family of Keir Starmer', 'rodney-starmer-is-family-of-keir-starmer', 'connection', false, NULL, NULL, 1962, 9, 2, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-7b99-4d34-bf9e-9a032bceeeb6', 'University of Leeds', 'university-of-leeds', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Law"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-7e04-4364-b8f9-e5c01217b362', 'Keir Starmer studied at University of Leeds', 'keir-starmer-studied-at-university-of-leeds', 'connection', false, NULL, NULL, 1981, NULL, NULL, 1985, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Law", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-8a67-4eb0-8e3a-ab0d8c5896ff', 'University of Oxford', 'university-of-oxford', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate", "course": "BCL"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-8c46-4d3c-8a9e-ae5c1c79f282', 'Keir Starmer studied at University of Oxford', 'keir-starmer-studied-at-university-of-oxford', 'connection', false, NULL, NULL, 1985, NULL, NULL, 1986, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate", "course": "BCL", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-9632-4903-b3fb-f8a4e5a2d775', 'Doughty Street Chambers', 'doughty-street-chambers', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Barrister"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-97ee-4d16-92f9-adbfe266dd8a', 'Keir Starmer worked at Doughty Street Chambers', 'keir-starmer-worked-at-doughty-street-chambers', 'connection', false, NULL, NULL, 1987, NULL, NULL, 2008, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Barrister", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-a0b7-42b1-bedc-40eb7e5360fd', 'Crown Prosecution Service', 'crown-prosecution-service', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Director of Public Prosecutions and Head of the Crown Prosecution Service"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-a21e-4911-a195-9f12c9182ed4', 'Keir Starmer worked at Crown Prosecution Service', 'keir-starmer-worked-at-crown-prosecution-service', 'connection', false, NULL, NULL, 2008, NULL, NULL, 2013, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Director of Public Prosecutions and Head of the Crown Prosecution Service", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-a9de-4cb5-8d11-155fd6c81484', 'UK Government', 'uk-government', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Member of Parliament for Holborn and St Pancras"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-ab2c-47b1-ba9b-e6c95834de90', 'Keir Starmer worked at UK Government', 'keir-starmer-worked-at-uk-government', 'connection', false, NULL, NULL, 2015, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Member of Parliament for Holborn and St Pancras", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-b2c9-45ab-a9e3-341272ad5c8f', 'Labour Party', 'labour-party', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Leader of the Labour Party"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-b41e-4a31-b375-ff0dc2cedee9', 'Keir Starmer worked at Labour Party', 'keir-starmer-worked-at-labour-party', 'connection', false, NULL, NULL, 2020, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Leader of the Labour Party", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-bb46-410c-a1d1-cd3af58f53e9', 'Oxted, UK', 'oxted-uk', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "birthplace and childhood home"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-bc9c-4a33-91de-62b59c4d96e9', 'Keir Starmer lived in Oxted, UK', 'keir-starmer-lived-in-oxted-uk', 'connection', false, NULL, NULL, 1962, 9, 2, 1981, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"reason": "birthplace and childhood home", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-c34e-4e98-8b9a-d13e6b6eaefa', 'Leeds, UK', 'leeds-uk', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "undergraduate studies"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-c485-4469-8032-684d573e44c7', 'Keir Starmer lived in Leeds, UK', 'keir-starmer-lived-in-leeds-uk', 'connection', false, NULL, NULL, 1981, NULL, NULL, 1985, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "undergraduate studies", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-cb39-4736-a8d5-6f0eb504a638', 'Oxford, UK', 'oxford-uk', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "postgraduate studies"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-cc8c-4f27-96fb-1d95b3c6c258', 'Keir Starmer lived in Oxford, UK', 'keir-starmer-lived-in-oxford-uk', 'connection', false, NULL, NULL, 1985, NULL, NULL, 1986, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "postgraduate studies", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-d3c9-4dcb-a67c-eca1d33811a7', 'Keir Starmer lived in London, UK', 'keir-starmer-lived-in-london-uk', 'connection', false, NULL, NULL, 1986, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "work and current residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-da39-44b5-bf77-43613897d9a4', 'Victoria Starmer', 'victoria-starmer', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32d-db84-4071-91fd-b7aed7fc1e12', 'Keir Starmer has relationship with Victoria Starmer', 'keir-starmer-has-relationship-with-victoria-starmer', 'connection', false, NULL, NULL, 2007, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:24', '2025-03-26 17:43:24', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-c0f6-4d06-949d-a44c1e9ac789', 'Kurt Vonnegut', 'kurt-vonnegut', 'person', false, NULL, NULL, 1922, 11, 11, 2007, 4, 11, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-c3dc-44eb-814c-e66eba89a4ff', 'Shortridge High School', 'shortridge-high-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-c6b9-41d7-b6ba-d7c844727974', 'Kurt Vonnegut studied at Shortridge High School', 'kurt-vonnegut-studied-at-shortridge-high-school', 'connection', false, NULL, NULL, 1936, NULL, NULL, 1940, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-de86-4143-a9d5-3b624fe15b1d', 'Cornell University', 'cornell-university', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "Biology and Chemistry"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a350-fb64-4dcf-8fb0-33fe4f2987de', 'The Beatles', 'the-beatles-2', 'band', false, NULL, NULL, 1960, 8, 17, 1970, 4, 10, 'day', 'day', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-e119-41a7-9610-842d211aae80', 'Kurt Vonnegut studied at Cornell University', 'kurt-vonnegut-studied-at-cornell-university', 'connection', false, NULL, NULL, 1940, NULL, NULL, 1943, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "Biology and Chemistry", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-ef67-44ac-b6b9-93cbf0a47aeb', 'Carnegie Institute of Technology (now Carnegie Mellon)', 'carnegie-institute-of-technology-now-carnegie-mellon', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "Mechanical Engineering"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-f18d-454b-8c14-b09f57c73c44', 'Kurt Vonnegut studied at Carnegie Institute of Technology (now Carnegie Mellon)', 'kurt-vonnegut-studied-at-carnegie-institute-of-technology-now-carnegie-mellon', 'connection', false, NULL, NULL, 1943, NULL, NULL, 1943, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "Mechanical Engineering", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-fce0-45ba-a073-e7babce3fed4', 'University of Tennessee', 'university-of-tennessee', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "Mechanical Engineering (U.S. Army Special Training Program)"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32e-fea4-4ee5-8147-6be628e26b58', 'Kurt Vonnegut studied at University of Tennessee', 'kurt-vonnegut-studied-at-university-of-tennessee', 'connection', false, NULL, NULL, 1943, NULL, NULL, 1944, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "Mechanical Engineering (U.S. Army Special Training Program)", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-07c5-4038-89d3-d090714ba84b', 'University of Chicago', 'university-of-chicago', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "honorary", "course": "Master’s Degree in Anthropology"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-0926-4782-bbc3-0d07586de6d0', 'Kurt Vonnegut studied at University of Chicago', 'kurt-vonnegut-studied-at-university-of-chicago', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "honorary", "course": "Master’s Degree in Anthropology", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-1151-44ef-bd12-70b2ada57c2b', 'General Electric', 'general-electric', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Public Relations Staff Writer", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-12bd-42df-801b-c6bd1635f4e8', 'Kurt Vonnegut worked at General Electric', 'kurt-vonnegut-worked-at-general-electric', 'connection', false, NULL, NULL, 1947, NULL, NULL, 1950, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Public Relations Staff Writer", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-1afe-44ac-96fd-48e8a5458ed0', 'Kurt Vonnegut worked at Self-employed', 'kurt-vonnegut-worked-at-self-employed', 'connection', false, NULL, NULL, 1950, NULL, NULL, 2007, 4, 11, 'year', 'day', 'placeholder', NULL, NULL, '{"role": "Author", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-224b-40d1-9620-b0685d71f78a', 'Indianapolis, Indiana, USA', 'indianapolis-indiana-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-2399-43db-91b6-c946b808340e', 'Kurt Vonnegut lived in Indianapolis, Indiana, USA', 'kurt-vonnegut-lived-in-indianapolis-indiana-usa', 'connection', false, NULL, NULL, 1922, 11, 11, 1943, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-2a8d-45cd-9050-ae9ebd3a9e65', 'Dresden, Germany (as a POW)', 'dresden-germany-as-a-pow', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-2bd8-432a-b67a-c8f3e6322e30', 'Kurt Vonnegut lived in Dresden, Germany (as a POW)', 'kurt-vonnegut-lived-in-dresden-germany-as-a-pow', 'connection', false, NULL, NULL, 1944, NULL, NULL, 1945, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-33a3-45be-825f-d49382001f51', 'Kurt Vonnegut lived in Chicago, Illinois, USA', 'kurt-vonnegut-lived-in-chicago-illinois-usa', 'connection', false, NULL, NULL, 1946, NULL, NULL, 1947, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-3a99-4dd5-9210-d9ce624392f2', 'Cape Cod, Massachusetts, USA', 'cape-cod-massachusetts-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-3be3-410d-9fda-936e487b7fbb', 'Kurt Vonnegut lived in Cape Cod, Massachusetts, USA', 'kurt-vonnegut-lived-in-cape-cod-massachusetts-usa', 'connection', false, NULL, NULL, 1950, NULL, NULL, 1971, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-42ea-4c38-af13-7df08178e536', 'Kurt Vonnegut lived in New York City, New York, USA', 'kurt-vonnegut-lived-in-new-york-city-new-york-usa', 'connection', false, NULL, NULL, 1971, NULL, NULL, 2007, 4, 11, 'year', 'day', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-4922-41bd-bc53-6afe43e4814f', 'Jane Marie Cox', 'jane-marie-cox', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-4a4d-4cc6-a9b8-9f9d6aac9d8d', 'Kurt Vonnegut has relationship with Jane Marie Cox', 'kurt-vonnegut-has-relationship-with-jane-marie-cox', 'connection', false, NULL, NULL, 1945, NULL, NULL, 1971, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship", "connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-5060-449f-9885-3a77044d01ae', 'Jill Krementz', 'jill-krementz', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a32f-5189-4fc0-b0d7-1a2e883a3cf0', 'Kurt Vonnegut has relationship with Jill Krementz', 'kurt-vonnegut-has-relationship-with-jill-krementz', 'connection', false, NULL, NULL, 1979, NULL, NULL, 2007, 4, 11, 'year', 'day', 'placeholder', NULL, NULL, '{"type": "relationship", "connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:25', '2025-03-26 17:43:25', NULL);
INSERT INTO public.spans VALUES ('9e86a330-3059-48ef-9911-fd5ea1c88713', 'Laurie Anderson', 'laurie-anderson', 'person', false, NULL, NULL, 1947, 6, 5, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:26', '2025-03-26 17:43:26', NULL);
INSERT INTO public.spans VALUES ('9e86a330-34db-46a0-8c1a-af22df602122', 'Laurie Anderson studied at Columbia University', 'laurie-anderson-studied-at-columbia-university', 'connection', false, NULL, NULL, 1969, NULL, NULL, 1972, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "graduate", "course": "Sculpture", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:26', '2025-03-26 17:43:26', NULL);
INSERT INTO public.spans VALUES ('9e86a330-4b0b-467a-b114-908fb229e83a', 'Laurie Anderson worked at Self-employed', 'laurie-anderson-worked-at-self-employed', 'connection', false, NULL, NULL, 1970, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Performance artist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:26', '2025-03-26 17:43:26', NULL);
INSERT INTO public.spans VALUES ('9e86a330-58f7-4497-8d30-1933bc81b1e5', 'Lou Reed', 'lou-reed', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:26', '2025-03-26 17:43:26', NULL);
INSERT INTO public.spans VALUES ('9e86a330-5b53-4108-b1a5-41f02a1dea2c', 'Laurie Anderson has relationship with Lou Reed', 'laurie-anderson-has-relationship-with-lou-reed', 'connection', false, NULL, NULL, 2008, NULL, NULL, 2013, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:26', '2025-03-26 17:43:26', NULL);
INSERT INTO public.spans VALUES ('9e86a331-4092-4f87-bd11-a58d3722d9fd', 'Lhaki Berger', 'lhaki-berger', 'person', false, NULL, NULL, 1979, 6, 17, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:27', '2025-03-26 17:43:27', NULL);
INSERT INTO public.spans VALUES ('9e86a333-4b1e-4d39-b5bf-11d43d714edf', 'Brighton and Hove High School', 'brighton-and-hove-high-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-4d35-424a-aff3-8465e7faa147', 'Lindsay Northover studied at Brighton and Hove High School', 'lindsay-northover-studied-at-brighton-and-hove-high-school', 'connection', false, NULL, NULL, 1965, NULL, NULL, 1972, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-3e0b-4018-b2da-ac4c571dc068', 'Lindsay Northover is family of Louisa Denison', 'lindsay-northover-is-family-of-louisa-denison', 'connection', false, NULL, NULL, 1993, 3, 28, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:29', NULL);
INSERT INTO public.spans VALUES ('9e86a331-45aa-45b2-ad2e-cd0002116ffa', 'Lhaki Berger is family of Pema Northover', 'lhaki-berger-is-family-of-pema-northover', 'connection', false, NULL, NULL, 2013, 1, 29, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:27', '2025-03-26 17:43:36', NULL);
INSERT INTO public.spans VALUES ('9e86a333-2967-4bf9-b630-26cde2800192', 'Lindsay Northover is family of Tom Northover', 'lindsay-northover-is-family-of-tom-northover', 'connection', false, NULL, NULL, 1989, 1, 23, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a333-57ed-485f-ae53-313375d1fb19', 'St Anne''s College, Oxford', 'st-annes-college-oxford', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "Modern History"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-59a9-46e7-ae03-5f42439df1e8', 'Lindsay Northover studied at St Anne''s College, Oxford', 'lindsay-northover-studied-at-st-annes-college-oxford', 'connection', false, NULL, NULL, 1972, NULL, NULL, 1976, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "Modern History", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-62e7-4d95-bc1f-6634f789e77b', 'Bryn Mawr College and University of Pennsylvania', 'bryn-mawr-college-and-university-of-pennsylvania', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "postgraduate", "course": "History and Philosophy of Science"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-6465-4766-930f-bb021972f517', 'Lindsay Northover studied at Bryn Mawr College and University of Pennsylvania', 'lindsay-northover-studied-at-bryn-mawr-college-and-university-of-pennsylvania', 'connection', false, NULL, NULL, 1976, NULL, NULL, 1981, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "postgraduate", "course": "History and Philosophy of Science", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-6d17-467a-9695-f7489f5714bc', 'University College London and St Mark''s Hospital', 'university-college-london-and-st-marks-hospital', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Research Fellow", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-6e77-4fde-bfa4-990a384b5720', 'Lindsay Northover worked at University College London and St Mark''s Hospital', 'lindsay-northover-worked-at-university-college-london-and-st-marks-hospital', 'connection', false, NULL, NULL, 1980, NULL, NULL, 1983, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Research Fellow", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-765b-47ec-95e6-772fb1ca16c8', 'St Thomas''s Hospital Medical School', 'st-thomass-hospital-medical-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Research Fellow", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-77a5-493b-9372-bb272ff00c92', 'Lindsay Northover worked at St Thomas''s Hospital Medical School', 'lindsay-northover-worked-at-st-thomass-hospital-medical-school', 'connection', false, NULL, NULL, 1983, NULL, NULL, 1984, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Research Fellow", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-7f4c-4e25-be49-47f78b329bd4', 'University College London and Wellcome Institute', 'university-college-london-and-wellcome-institute', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lecturer", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-8094-40ba-acfb-6ced9decfd8d', 'Lindsay Northover worked at University College London and Wellcome Institute', 'lindsay-northover-worked-at-university-college-london-and-wellcome-institute', 'connection', false, NULL, NULL, 1984, NULL, NULL, 1991, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lecturer", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-87ef-4f93-848d-7d726c46194f', 'House of Lords', 'house-of-lords', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Member", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-8918-4cec-abaf-b19ad20e828b', 'Lindsay Northover worked at House of Lords', 'lindsay-northover-worked-at-house-of-lords', 'connection', false, NULL, NULL, 2000, 5, 10, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"role": "Member", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-8fdb-4af3-99af-3b8ca67dfa46', 'Department for International Development', 'department-for-international-development', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Parliamentary Under-Secretary of State", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-90e4-49f4-9aa8-95c5a9567798', 'Lindsay Northover worked at Department for International Development', 'lindsay-northover-worked-at-department-for-international-development', 'connection', false, NULL, NULL, 2014, 11, 4, 2015, 5, 7, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Parliamentary Under-Secretary of State", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-9777-4c9a-a1f0-235df757dca9', 'Brighton, England', 'brighton-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-98b1-480a-b38e-239696af3ceb', 'Lindsay Northover lived in Brighton, England', 'lindsay-northover-lived-in-brighton-england', 'connection', false, NULL, NULL, 1954, 8, 21, 1972, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-9f6b-41c9-9a7b-8e408d200017', 'Oxford, England', 'oxford-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-a0ab-4304-b86d-7efcecb72b8b', 'Lindsay Northover lived in Oxford, England', 'lindsay-northover-lived-in-oxford-england', 'connection', false, NULL, NULL, 1972, NULL, NULL, 1976, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-a768-4276-90e6-6a0f20a59b20', 'Philadelphia, Pennsylvania, USA', 'philadelphia-pennsylvania-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-a88c-4339-88b3-18424a7c53bc', 'Lindsay Northover lived in Philadelphia, Pennsylvania, USA', 'lindsay-northover-lived-in-philadelphia-pennsylvania-usa', 'connection', false, NULL, NULL, 1976, NULL, NULL, 1981, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-af2c-414f-9dcd-54de81b91258', 'London, England', 'london-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-b04b-454a-8685-7b7c809ed987', 'Lindsay Northover lived in London, England', 'lindsay-northover-lived-in-london-england', 'connection', false, NULL, NULL, 1981, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a333-b76c-47e4-bab9-4f855b4714b4', 'Lindsay Northover has relationship with John Northover', 'lindsay-northover-has-relationship-with-john-northover', 'connection', false, NULL, NULL, 1984, NULL, NULL, 2015, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship", "connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:28', '2025-03-26 17:43:28', NULL);
INSERT INTO public.spans VALUES ('9e86a335-a639-4d6b-9600-63a71aa0142b', 'Matthew Shorter', 'matthew-shorter', 'person', false, NULL, NULL, 1976, 2, 13, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a335-a8e4-4b37-8d8a-244c216d0ea4', 'Unthinkable', 'unthinkable', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Director"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a335-aaa0-4591-9788-e57ab9afa5b2', 'Matthew Shorter worked at Unthinkable', 'matthew-shorter-worked-at-unthinkable', 'connection', false, NULL, NULL, 2010, 1, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"role": "Director", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a336-9642-4ba4-977e-ed5ac6849642', 'Max Richter', 'max-richter', 'person', false, NULL, NULL, 1966, 3, 22, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a336-99e8-426a-8c6c-2bd9754f6feb', 'The University of Edinburgh', 'the-university-of-edinburgh', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Composition and Piano"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a336-9dde-4e76-a05d-53ccd732975a', 'Max Richter studied at The University of Edinburgh', 'max-richter-studied-at-the-university-of-edinburgh', 'connection', false, NULL, NULL, 1984, NULL, NULL, 1988, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Composition and Piano", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a336-b9a7-4ea4-9401-2a0b38dffa6c', 'Royal Academy of Music', 'royal-academy-of-music', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate", "course": "Composition"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a336-bd2d-4721-9c56-732f3d1f7375', 'Max Richter studied at Royal Academy of Music', 'max-richter-studied-at-royal-academy-of-music', 'connection', false, NULL, NULL, 1988, NULL, NULL, 1991, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "postgraduate", "course": "Composition", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a336-d198-47b9-9d4f-ac81e6474a75', 'Max Richter worked at Freelance', 'max-richter-worked-at-freelance', 'connection', false, NULL, NULL, 1991, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Composer", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:30', '2025-03-26 17:43:30', NULL);
INSERT INTO public.spans VALUES ('9e86a337-bd41-4836-8e1d-05e4703f0747', 'Michael Stipe', 'michael-stipe', 'person', false, NULL, NULL, 1960, 1, 4, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a337-c0cf-481f-b1a2-45eb0f233f0e', 'University of Georgia', 'university-of-georgia', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "higher education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a337-c4ab-4791-a84e-34bcaf2c98b4', 'Michael Stipe studied at University of Georgia', 'michael-stipe-studied-at-university-of-georgia', 'connection', false, NULL, NULL, 1980, NULL, NULL, 1982, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "higher education", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a337-e080-43d4-aad6-bdc59990ccbd', 'R.E.M.', 'rem', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead vocalist"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a337-e448-4e61-a9de-1826fc8a5655', 'Michael Stipe worked at R.E.M.', 'michael-stipe-worked-at-rem', 'connection', false, NULL, NULL, 1980, NULL, NULL, 2011, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead vocalist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a337-f5d7-4ba0-87c3-92aa4522c429', 'Decatur, Georgia, USA', 'decatur-georgia-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Birth and early life"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a337-f7d5-47a7-9ccb-c80421997434', 'Michael Stipe lived in Decatur, Georgia, USA', 'michael-stipe-lived-in-decatur-georgia-usa', 'connection', false, NULL, NULL, 1960, NULL, NULL, 1980, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Birth and early life", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a338-019e-4efd-a914-91cb4fbe243c', 'Athens, Georgia, USA', 'athens-georgia-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Career and current residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a338-0303-4dd4-b457-bbd427d57987', 'Michael Stipe lived in Athens, Georgia, USA', 'michael-stipe-lived-in-athens-georgia-usa', 'connection', false, NULL, NULL, 1980, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Career and current residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:31', '2025-03-26 17:43:31', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-909c-4aac-b0cd-3e1962de266d', 'Nick Drake', 'nick-drake', 'person', false, NULL, NULL, 1948, 6, 19, 1974, 11, 25, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-94ba-4cc3-97f2-099593ff553c', 'Eagle House School', 'eagle-house-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-9939-4b43-bd58-5cb5d1a69c57', 'Nick Drake studied at Eagle House School', 'nick-drake-studied-at-eagle-house-school', 'connection', false, NULL, NULL, 1957, NULL, NULL, 1961, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-b483-4969-aaf7-cc50d2ad5933', 'Marlborough College', 'marlborough-college', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-b848-4c90-8fd5-0692b24a1656', 'Nick Drake studied at Marlborough College', 'nick-drake-studied-at-marlborough-college', 'connection', false, NULL, NULL, 1961, NULL, NULL, 1966, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-ca11-4001-aded-62019f82a541', 'Fitzwilliam College, Cambridge', 'fitzwilliam-college-cambridge', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "English Literature"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-cc74-4049-9272-589966cde076', 'Nick Drake studied at Fitzwilliam College, Cambridge', 'nick-drake-studied-at-fitzwilliam-college-cambridge', 'connection', false, NULL, NULL, 1967, NULL, NULL, 1969, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "tertiary", "course": "English Literature", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-da1c-45db-998a-fd7e15756f65', 'Nick Drake worked at Self-employed', 'nick-drake-worked-at-self-employed', 'connection', false, NULL, NULL, 1968, NULL, NULL, 1974, 11, 25, 'year', 'day', 'placeholder', NULL, NULL, '{"role": "Singer-Songwriter", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-e491-4307-8b03-cfa8f6151d80', 'Rangoon, Burma (Myanmar)', 'rangoon-burma-myanmar', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-e646-4d0d-a8a2-0e6e2e65dbdd', 'Nick Drake lived in Rangoon, Burma (Myanmar)', 'nick-drake-lived-in-rangoon-burma-myanmar', 'connection', false, NULL, NULL, 1948, NULL, NULL, 1950, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-f02a-4d6f-b39b-7aff2129c062', 'Tanworth-in-Arden, Warwickshire, England', 'tanworth-in-arden-warwickshire-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33a-f1b4-410a-86e9-18190c6e2375', 'Nick Drake lived in Tanworth-in-Arden, Warwickshire, England', 'nick-drake-lived-in-tanworth-in-arden-warwickshire-england', 'connection', false, NULL, NULL, 1950, NULL, NULL, 1974, 11, 25, 'year', 'day', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:33', '2025-03-26 17:43:33', NULL);
INSERT INTO public.spans VALUES ('9e86a33b-d590-4ff2-a44d-9281e5d0f93a', 'Nirvana', 'nirvana', 'band', false, NULL, NULL, 1987, 1, 1, 1994, 4, 5, 'day', 'day', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33b-d86f-4921-ba5a-b86e1b0310ea', 'Kurt Cobain', 'kurt-cobain', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Vocals, Guitar"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33b-dc4c-48c0-951d-2ae99bef3c9b', 'Kurt Cobain was member of Nirvana', 'kurt-cobain-was-member-of-nirvana', 'connection', false, NULL, NULL, 1987, 1, 1, 1994, 4, 5, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Lead Vocals, Guitar", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33b-f88f-4cd3-800c-be2fabaeebb3', 'Krist Novoselic', 'krist-novoselic', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Bass"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33b-fc51-4e1a-bd57-602833a887fe', 'Krist Novoselic was member of Nirvana', 'krist-novoselic-was-member-of-nirvana', 'connection', false, NULL, NULL, 1987, 1, 1, 1994, 4, 5, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Bass", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33c-0c07-4776-a92b-76582e48231d', 'Dave Grohl', 'dave-grohl', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Drums"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33c-0e38-4129-8b3c-7d110c0ff6e4', 'Dave Grohl was member of Nirvana', 'dave-grohl-was-member-of-nirvana', 'connection', false, NULL, NULL, 1990, 10, 14, 1994, 4, 5, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Drums", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33c-efc2-4db9-9106-610021dd8193', 'Paul McCartney', 'paul-mccartney', 'person', false, NULL, NULL, 1942, 6, 17, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33c-f185-4103-a5b1-6ab70944dd54', 'Stockton Wood Road Primary School', 'stockton-wood-road-primary-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33c-f3fd-481c-b9ba-fc124a07a140', 'Paul McCartney studied at Stockton Wood Road Primary School', 'paul-mccartney-studied-at-stockton-wood-road-primary-school', 'connection', false, NULL, NULL, 1947, NULL, NULL, 1949, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-0856-44e8-a4bb-767801c18b37', 'Joseph Williams Junior School', 'joseph-williams-junior-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-09b4-4cf9-9b50-8dd42addcd61', 'Paul McCartney studied at Joseph Williams Junior School', 'paul-mccartney-studied-at-joseph-williams-junior-school', 'connection', false, NULL, NULL, 1949, NULL, NULL, 1953, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-138d-4844-a7b7-ce0d228bdaba', 'Paul McCartney studied at Liverpool Institute High School for Boys', 'paul-mccartney-studied-at-liverpool-institute-high-school-for-boys', 'connection', false, NULL, NULL, 1953, NULL, NULL, 1960, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "education", "level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-1f49-47ca-9779-73e06c6221aa', 'Paul McCartney worked at The Beatles', 'paul-mccartney-worked-at-the-beatles', 'connection', false, NULL, NULL, 1960, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Co-Lead Vocalist and Bassist", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:34', '2025-03-26 17:43:34', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-2a76-4abe-b26c-70213b0ed223', 'Wings', 'wings', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Vocalist and Multi-Instrumentalist", "type": "work"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a35d-0739-42ed-81df-68a56ef03337', 'Government of Russia', 'government-of-russia', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "President of Russia"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-2c0b-4b32-a8ce-9d3adf1bf8b2', 'Paul McCartney worked at Wings', 'paul-mccartney-worked-at-wings', 'connection', false, NULL, NULL, 1971, NULL, NULL, 1981, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Vocalist and Multi-Instrumentalist", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-375a-4b42-b7de-9becfeaa39fe', 'Paul McCartney worked at Self-employed', 'paul-mccartney-worked-at-self-employed', 'connection', false, NULL, NULL, 1970, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Solo Artist", "type": "work", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-41d9-439b-8688-331c1f7710ed', 'Paul McCartney lived in Liverpool, England', 'paul-mccartney-lived-in-liverpool-england', 'connection', false, NULL, NULL, 1942, 6, 18, 1963, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-4c25-4948-a6d7-19ef82274ad2', 'Paul McCartney lived in London, England', 'paul-mccartney-lived-in-london-england', 'connection', false, NULL, NULL, 1963, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-547d-427e-8da4-1f5c53ff6310', 'East Sussex, England', 'east-sussex-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-55b9-457a-8c4c-1ba4bacda734', 'Paul McCartney lived in East Sussex, England', 'paul-mccartney-lived-in-east-sussex-england', 'connection', false, NULL, NULL, 1970, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-5e75-4731-bea2-493665c2d9b7', 'Paul McCartney lived in New York City, New York, USA', 'paul-mccartney-lived-in-new-york-city-new-york-usa', 'connection', false, NULL, NULL, 2002, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "residence", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-65d4-4bd2-9aa3-1be108c4f5f9', 'Linda Eastman', 'linda-eastman', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-6720-4891-bb85-f24e67222e2d', 'Paul McCartney has relationship with Linda Eastman', 'paul-mccartney-has-relationship-with-linda-eastman', 'connection', false, NULL, NULL, 1969, 3, 12, 1998, 4, 17, 'day', 'day', 'placeholder', NULL, NULL, '{"type": "relationship", "connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-6f28-45f8-b3dc-cdcd245db913', 'Heather Mills', 'heather-mills', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-705c-498b-8b4a-b3ea45433d73', 'Paul McCartney has relationship with Heather Mills', 'paul-mccartney-has-relationship-with-heather-mills', 'connection', false, NULL, NULL, 2002, 6, 11, 2008, 3, 17, 'day', 'day', 'placeholder', NULL, NULL, '{"type": "relationship", "connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-7867-448a-9b36-9766079d19e2', 'Nancy Shevell', 'nancy-shevell', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a33d-7991-42a9-8d1f-2a1c64cb70fa', 'Paul McCartney has relationship with Nancy Shevell', 'paul-mccartney-has-relationship-with-nancy-shevell', 'connection', false, NULL, NULL, 2011, 10, 9, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"type": "relationship", "connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:35', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a30c-78ea-4842-823d-4239a1cc6b61', 'Peggy Northover is family of Chris Northover', 'peggy-northover-is-family-of-chris-northover', 'connection', false, NULL, NULL, 1951, 2, 2, 2014, 7, 15, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:03', '2025-03-26 17:43:35', NULL);
INSERT INTO public.spans VALUES ('9e86a309-4ad2-4c20-8781-ac7874ee5210', 'Benn Northover is family of Pema Northover', 'benn-northover-is-family-of-pema-northover', 'connection', false, NULL, NULL, 2013, 1, 29, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:01', '2025-03-26 17:43:36', NULL);
INSERT INTO public.spans VALUES ('9e86a340-7b54-4453-b74b-e33892278a5d', 'Portishead', 'portishead-2', 'band', false, NULL, NULL, 1991, 1, 1, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a340-8075-452d-bc4e-797df51ab664', 'Beth Gibbons was member of Portishead', 'beth-gibbons-was-member-of-portishead', 'connection', false, NULL, NULL, 1991, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "vocals", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a340-9b45-46e9-9935-720fb5faf7bf', 'Geoff Barrow', 'geoff-barrow', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "instrumentalist"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a340-9f45-450b-a991-425d87be039c', 'Geoff Barrow was member of Portishead', 'geoff-barrow-was-member-of-portishead', 'connection', false, NULL, NULL, 1991, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "instrumentalist", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a340-afbe-4855-9016-355aeedd8624', 'Adrian Utley', 'adrian-utley', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "instrumentalist"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a340-b1e5-4c13-ac7a-9b1b7e05865d', 'Adrian Utley was member of Portishead', 'adrian-utley-was-member-of-portishead', 'connection', false, NULL, NULL, 1991, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "instrumentalist", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a341-97ea-4174-a7d3-7c44700210a3', 'R.E.M.', 'rem-2', 'band', false, NULL, NULL, 1980, 1, 7, 2011, 9, 21, 'day', 'day', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a341-9c2b-4760-9ce4-41d9ee102a5e', 'Michael Stipe was member of R.E.M.', 'michael-stipe-was-member-of-rem', 'connection', false, NULL, NULL, 1980, 1, 7, 2011, 9, 21, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Lead vocals", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a341-b6fa-4d56-a5ae-83a5442a57cc', 'Peter Buck', 'peter-buck', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Guitar"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:37', '2025-03-26 17:43:37', NULL);
INSERT INTO public.spans VALUES ('9e86a341-bb52-4402-90de-2a7dc32976ba', 'Peter Buck was member of R.E.M.', 'peter-buck-was-member-of-rem', 'connection', false, NULL, NULL, 1980, 1, 7, 2011, 9, 21, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Guitar", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a341-cbb4-4ebc-8cf8-8b5dbef63df7', 'Mike Mills', 'mike-mills', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Bass guitar, backing vocals"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-75aa-45be-87a1-9162174da60a', 'Mary Soames, Baroness Soames', 'mary-soames-baroness-soames', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a341-ce1e-4bb3-8ecc-ea1971277562', 'Mike Mills was member of R.E.M.', 'mike-mills-was-member-of-rem', 'connection', false, NULL, NULL, 1980, 1, 7, 2011, 9, 21, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Bass guitar, backing vocals", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a341-da69-40b6-8120-a6ed82c08a51', 'Bill Berry', 'bill-berry', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Drums"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a341-dc16-4752-a1f5-6ed9587d2257', 'Bill Berry was member of R.E.M.', 'bill-berry-was-member-of-rem', 'connection', false, NULL, NULL, 1980, 1, 7, 1997, 7, 1, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Drums", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a342-bd97-447c-8f37-14123ed29b85', 'Radiohead', 'radiohead', 'band', false, NULL, NULL, 1985, 1, 1, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a342-c52c-4e27-9138-1fa8ecc3b8c7', 'Thom Yorke was member of Radiohead', 'thom-yorke-was-member-of-radiohead', 'connection', false, NULL, NULL, 1985, 1, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"role": "Lead vocals, guitar, piano", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a342-e04f-4634-8836-011494e95d63', 'Jonny Greenwood', 'jonny-greenwood', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead guitar, keyboards"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a342-e452-4416-93eb-d2479d25315f', 'Jonny Greenwood was member of Radiohead', 'jonny-greenwood-was-member-of-radiohead', 'connection', false, NULL, NULL, 1985, 1, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"role": "Lead guitar, keyboards", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a342-f619-4820-953a-9016284fa360', 'Colin Greenwood', 'colin-greenwood', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Bass guitar"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a342-f86a-4476-a7df-6023d52b5734', 'Colin Greenwood was member of Radiohead', 'colin-greenwood-was-member-of-radiohead', 'connection', false, NULL, NULL, 1985, 1, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"role": "Bass guitar", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a343-04cc-4c8c-ac54-721e056ac804', 'Ed O''Brien', 'ed-obrien', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Guitar, backing vocals"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a343-068d-474d-a474-a17258e2c6ec', 'Ed O''Brien was member of Radiohead', 'ed-obrien-was-member-of-radiohead', 'connection', false, NULL, NULL, 1985, 1, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"role": "Guitar, backing vocals", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a343-10d2-46a0-be81-840c5cdeffcf', 'Philip Selway', 'philip-selway', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Drums"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a343-1278-402a-a5de-1c4d236a708e', 'Philip Selway was member of Radiohead', 'philip-selway-was-member-of-radiohead', 'connection', false, NULL, NULL, 1985, 1, 1, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"role": "Drums", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:38', NULL);
INSERT INTO public.spans VALUES ('9e86a343-f659-442d-aad0-1dcdfcfefbff', 'Richard Dawkins', 'richard-dawkins', 'person', false, NULL, NULL, 1941, 3, 26, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a343-f9ef-452d-b0f2-f7331573e30e', 'Jean Mary Vyvyan Ladner', 'jean-mary-vyvyan-ladner', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a343-fd92-4635-93b8-7a7a5b86e69b', 'Jean Mary Vyvyan Ladner is family of Richard Dawkins', 'jean-mary-vyvyan-ladner-is-family-of-richard-dawkins', 'connection', false, NULL, NULL, 1941, 3, 26, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-1782-46c7-bdfa-1424b0cd8988', 'Clinton John Dawkins', 'clinton-john-dawkins', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-1ac0-46da-8336-d9977c30ea74', 'Clinton John Dawkins is family of Richard Dawkins', 'clinton-john-dawkins-is-family-of-richard-dawkins', 'connection', false, NULL, NULL, 1941, 3, 26, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-29b5-4b67-9571-3c62a9eb1773', 'Juliet Emma Dawkins', 'juliet-emma-dawkins', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-2b90-4468-aaae-362b2a4ba19c', 'Richard Dawkins is family of Juliet Emma Dawkins', 'richard-dawkins-is-family-of-juliet-emma-dawkins', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-36cb-4fbc-9a4c-0d119ea8844c', 'Oundle School', 'oundle-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-3834-4289-94ea-9db7197977f4', 'Richard Dawkins studied at Oundle School', 'richard-dawkins-studied-at-oundle-school', 'connection', false, NULL, NULL, 1954, NULL, NULL, 1959, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-4202-4ab4-a5c5-53cef93ff477', 'Balliol College, Oxford', 'balliol-college-oxford', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-4374-485e-8f5d-29590be87336', 'Richard Dawkins studied at Balliol College, Oxford', 'richard-dawkins-studied-at-balliol-college-oxford', 'connection', false, NULL, NULL, 1959, NULL, NULL, 1962, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a342-c00d-45af-92c4-2f9b0e8082d5', 'Thom Yorke', 'thom-yorke', 'person', false, NULL, NULL, 1968, 10, 7, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"role": "Lead vocals, guitar, piano", "gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:38', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a344-4cd9-426f-928b-983922c9af5f', 'Richard Dawkins studied at University of Oxford', 'richard-dawkins-studied-at-university-of-oxford', 'connection', false, NULL, NULL, 1962, NULL, NULL, 1966, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-55be-4f82-8071-6bcbd44d95cf', 'Nairobi, British Kenya', 'nairobi-british-kenya', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-5703-4029-b465-35f1a4d4d3e9', 'Richard Dawkins lived in Nairobi, British Kenya', 'richard-dawkins-lived-in-nairobi-british-kenya', 'connection', false, NULL, NULL, 1941, 3, 26, 1949, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-5f97-46ed-b8b6-5577b76db3fd', 'Richard Dawkins lived in Oxford, England', 'richard-dawkins-lived-in-oxford-england', 'connection', false, NULL, NULL, 1959, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-66a7-4ee6-8e2d-d20c3874b736', 'Marian Stamp Dawkins', 'marian-stamp-dawkins', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-67f2-4bc0-ac1b-01ec8dba6250', 'Richard Dawkins has relationship with Marian Stamp Dawkins', 'richard-dawkins-has-relationship-with-marian-stamp-dawkins', 'connection', false, NULL, NULL, 1967, NULL, NULL, 1984, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-6f3f-4e60-8b90-c09d3238c42e', 'Eve Barham', 'eve-barham', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-7084-4558-ab5e-20b13fb8ac52', 'Richard Dawkins has relationship with Eve Barham', 'richard-dawkins-has-relationship-with-eve-barham', 'connection', false, NULL, NULL, 1984, NULL, NULL, 1992, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-77a2-4c9d-b8dd-3684f3d547a7', 'Lalla Ward', 'lalla-ward', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a344-78ea-4e53-b700-6643905af7c4', 'Richard Dawkins has relationship with Lalla Ward', 'richard-dawkins-has-relationship-with-lalla-ward', 'connection', false, NULL, NULL, 1992, NULL, NULL, 2016, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:39', '2025-03-26 17:43:39', NULL);
INSERT INTO public.spans VALUES ('9e86a345-84e2-4c62-9e2e-4a307a61cafe', 'St Saviours', 'st-saviours', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-87b0-4066-8673-3e140c97f8c6', 'Richard Northover studied at St Saviours', 'richard-northover-studied-at-st-saviours', 'connection', false, NULL, NULL, 1980, 9, NULL, 1987, 6, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-97a4-4946-a66f-533baebff773', 'Alleyn''s', 'alleyns', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-9940-4dd2-aed8-c5efa80323e2', 'Richard Northover studied at Alleyn''s', 'richard-northover-studied-at-alleyns', 'connection', false, NULL, NULL, 1987, 9, NULL, 1994, 6, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-a4d5-4ffc-9329-244220433425', 'Richard Northover studied at The University of Edinburgh', 'richard-northover-studied-at-the-university-of-edinburgh', 'connection', false, NULL, NULL, 1994, 10, NULL, 1997, 6, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Biological Sciences, Zoology", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-b01c-4fde-9daf-972b8adbcfb4', 'Richard Northover', 'richard-northover-2', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Freelance"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-6103-4701-9bc4-e8f5a2ab5f13', 'Sheila Northover is family of Richard Northover', 'sheila-northover-is-family-of-richard-northover', 'connection', false, NULL, NULL, 1976, 2, 13, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:44', NULL);
INSERT INTO public.spans VALUES ('9e86a345-bc02-4cfe-87fb-c5ea99199eec', 'Richard Northover worked at The University of Edinburgh', 'richard-northover-worked-at-the-university-of-edinburgh', 'connection', false, NULL, NULL, 2005, 1, NULL, 2006, 2, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"role": "Web Content Developer", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a355-8d79-4dc6-b853-5836f5bf5dd9', 'Abingdon School', 'abingdon-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "course": "Fine Arts"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a345-c54f-4bd6-ba28-56652ed7cb11', 'Richard Northover worked at BBC', 'richard-northover-worked-at-bbc', 'connection', false, NULL, NULL, 2006, 2, NULL, 2015, 7, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"role": "Service Owner, BBC iD", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-cd19-4e48-9bef-5747354107c5', 'Elsevier', 'elsevier', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Product Director, Identity"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-ce40-4b82-9119-b70b5d1d1984', 'Richard Northover worked at Elsevier', 'richard-northover-worked-at-elsevier', 'connection', false, NULL, NULL, 2015, 9, NULL, 2022, 5, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"role": "Product Director, Identity", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-b1b2-4265-acea-b31f47a24c19', 'Richard Northover worked at Richard Northover', 'richard-northover-worked-at-richard-northover', 'connection', false, NULL, NULL, 2022, 5, NULL, 2005, 12, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"role": "Freelance", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-d77d-4259-8aff-471d7491d604', 'London', 'london', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "childhood"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-d8b7-4008-8782-b142349b4074', 'Richard Northover lived in London', 'richard-northover-lived-in-london', 'connection', false, NULL, NULL, 1976, 2, NULL, 1994, 9, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"reason": "childhood", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-dffb-407e-960d-14dff1553a86', 'Edinburgh', 'edinburgh', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "university and early career"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-e122-43de-9ac5-c43fca94b20e', 'Richard Northover lived in Edinburgh', 'richard-northover-lived-in-edinburgh', 'connection', false, NULL, NULL, 1994, 9, NULL, 2006, 2, NULL, 'month', 'month', 'placeholder', NULL, NULL, '{"reason": "university and early career", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-e96c-4692-9a84-63469809919b', 'Richard Northover lived in London', 'richard-northover-lived-in-london-2', 'connection', false, NULL, NULL, 2006, 2, NULL, NULL, NULL, NULL, 'month', 'year', 'placeholder', NULL, NULL, '{"reason": "career", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-f0ff-484c-9155-5d3b1d981e3c', 'Nonny Denny', 'nonny-denny', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-f233-462d-a7e1-3f169f345de3', 'Richard Northover has relationship with Nonny Denny', 'richard-northover-has-relationship-with-nonny-denny', 'connection', false, NULL, NULL, 1995, NULL, NULL, 1997, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-f990-4ef6-b4fd-4070cea58f73', 'Kat Cooper', 'kat-cooper', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a345-fab6-42ea-b198-6a7b14bf07fc', 'Richard Northover has relationship with Kat Cooper', 'richard-northover-has-relationship-with-kat-cooper', 'connection', false, NULL, NULL, 1999, NULL, NULL, 2002, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a346-02e3-4993-a2e4-fa87e1a489a5', 'Richard Northover has relationship with Jenny McInnes', 'richard-northover-has-relationship-with-jenny-mcinnes', 'connection', false, NULL, NULL, 2002, 11, 13, 2023, 2, 13, 'day', 'day', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:40', '2025-03-26 17:43:40', NULL);
INSERT INTO public.spans VALUES ('9e86a346-e44e-4bc2-b535-a4caaaed9499', 'Ringo Starr', 'ringo-starr', 'person', false, NULL, NULL, 1940, 7, 7, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a346-e7ec-44d0-b584-22a9ab0956fc', 'Elsie Starkey', 'elsie-starkey', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a346-eb25-4120-9464-389d0d91668d', 'Elsie Starkey is family of Ringo Starr', 'elsie-starkey-is-family-of-ringo-starr', 'connection', false, NULL, NULL, 1940, 7, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-06bd-4754-aa71-17e0b915349d', 'Richard Starkey', 'richard-starkey', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-09df-41da-9759-e001aac8c74e', 'Richard Starkey is family of Ringo Starr', 'richard-starkey-is-family-of-ringo-starr', 'connection', false, NULL, NULL, 1940, 7, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-1ae3-428d-9f17-2c79abbd8c27', 'Zak Starkey', 'zak-starkey', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-1ca3-44ab-a3ed-6ccd2ff5a185', 'Ringo Starr is family of Zak Starkey', 'ringo-starr-is-family-of-zak-starkey', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-2838-41b7-a468-c9778a2a6b42', 'Jason Starkey', 'jason-starkey', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-29b4-4114-a576-dc035b4c171e', 'Ringo Starr is family of Jason Starkey', 'ringo-starr-is-family-of-jason-starkey', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-3364-4804-a524-3462b0a5d986', 'Lee Starkey', 'lee-starkey', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-34a1-4bc7-a8de-f47fa746f468', 'Ringo Starr is family of Lee Starkey', 'ringo-starr-is-family-of-lee-starkey', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-3d3e-4783-8034-59ba1512af07', 'Francesca Gregorini', 'francesca-gregorini', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-3e5f-4e49-a250-8d00d37d19a5', 'Ringo Starr is family of Francesca Gregorini', 'ringo-starr-is-family-of-francesca-gregorini', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-469a-40bc-8498-5525d2aeca7b', 'Ringo Starr worked at The Beatles', 'ringo-starr-worked-at-the-beatles', 'connection', false, NULL, NULL, 1962, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Drummer for Beatles", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-4e72-4ba2-a540-a8cc7f50a2fe', 'Ringo Starr', 'ringo-starr-2', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Solo Artist"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-4f9f-4620-b35b-499dc136d73d', 'Ringo Starr worked at Ringo Starr', 'ringo-starr-worked-at-ringo-starr', 'connection', false, NULL, NULL, 1970, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Solo Artist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-57e7-4762-84e5-55d2656d581d', 'Ringo Starr lived in Liverpool, England', 'ringo-starr-lived-in-liverpool-england', 'connection', false, NULL, NULL, 1940, NULL, NULL, 1962, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-6010-4a20-ba91-7296a5861ac8', 'Ringo Starr lived in Los Angeles, USA', 'ringo-starr-lived-in-los-angeles-usa', 'connection', false, NULL, NULL, 1970, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-6793-4189-bab1-a80b0ce68014', 'Maureen Starkey Tigrett', 'maureen-starkey-tigrett', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-68ea-499b-aa8d-e521d5678d50', 'Ringo Starr has relationship with Maureen Starkey Tigrett', 'ringo-starr-has-relationship-with-maureen-starkey-tigrett', 'connection', false, NULL, NULL, 1965, NULL, NULL, 1975, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-70c3-4351-b7e3-72e19fa4baaa', 'Barbara Bach', 'barbara-bach', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a347-71f1-4ea0-b2d7-15357e862b69', 'Ringo Starr has relationship with Barbara Bach', 'ringo-starr-has-relationship-with-barbara-bach', 'connection', false, NULL, NULL, 1981, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:41', '2025-03-26 17:43:41', NULL);
INSERT INTO public.spans VALUES ('9e86a34e-9ddf-47c9-a82a-62f61f99785f', 'The Magnetic Fields', 'the-magnetic-fields', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Singer, Songwriter"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:46', '2025-03-26 17:43:46', NULL);
INSERT INTO public.spans VALUES ('9e86a31f-7081-4c06-8a4b-ade50c987ea3', 'Jack Northover is family of River Northover', 'jack-northover-is-family-of-river-northover', 'connection', false, NULL, NULL, 2019, 10, 28, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:15', '2025-03-26 17:43:42', NULL);
INSERT INTO public.spans VALUES ('9e86a34e-a0a4-4f14-b194-8def0991f714', 'Stephin Merritt worked at The Magnetic Fields', 'stephin-merritt-worked-at-the-magnetic-fields', 'connection', false, NULL, NULL, 1989, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Singer, Songwriter", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:46', '2025-03-26 17:43:46', NULL);
INSERT INTO public.spans VALUES ('9e86a34b-9d00-4c7a-ae36-d2645b31c228', 'Sheila Northover is family of Simon Northover', 'sheila-northover-is-family-of-simon-northover', 'connection', false, NULL, NULL, 1978, 8, 15, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:44', '2025-03-26 17:43:45', NULL);
INSERT INTO public.spans VALUES ('9e86a34a-78d5-491f-8a09-cdfb2967b23f', 'Simon Northover is family of Scott Northover', 'simon-northover-is-family-of-scott-northover', 'connection', false, NULL, NULL, 2009, 6, 9, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:43', '2025-03-26 17:43:45', NULL);
INSERT INTO public.spans VALUES ('9e86a348-7043-4199-8946-cd1f6fbadab0', 'Sophie Northover is family of River Northover', 'sophie-northover-is-family-of-river-northover', 'connection', false, NULL, NULL, 2019, 10, 28, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:42', '2025-03-26 17:43:45', NULL);
INSERT INTO public.spans VALUES ('9e86a34e-9b28-43fa-8ddb-363dff4392b0', 'Stephin Merritt', 'stephin-merritt', 'person', false, NULL, NULL, 1965, 1, 1, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:46', '2025-03-26 17:43:46', NULL);
INSERT INTO public.spans VALUES ('9e86a34e-b7c0-49f9-8f29-3ce4d7e5b84f', 'Stephin Merritt worked at Self-Employed', 'stephin-merritt-worked-at-self-employed', 'connection', false, NULL, NULL, 2011, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Author", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:46', '2025-03-26 17:43:46', NULL);
INSERT INTO public.spans VALUES ('9e86a34e-c732-411e-9f74-866440efb795', 'Stephin Merritt lived in New York, USA', 'stephin-merritt-lived-in-new-york-usa', 'connection', false, NULL, NULL, 1990, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:46', '2025-03-26 17:43:46', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-aae4-4ba0-9c48-ac874058e157', 'Taylor Swift', 'taylor-swift', 'person', false, NULL, NULL, 1989, 12, 13, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-adee-4685-84a7-f0074e864af9', 'Andrea Swift', 'andrea-swift', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-b1ea-4e1c-a36e-8e11db0a71b8', 'Andrea Swift is family of Taylor Swift', 'andrea-swift-is-family-of-taylor-swift', 'connection', false, NULL, NULL, 1989, 12, 13, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-d06d-470c-a05b-65f3e718637e', 'Scott Kingsley Swift', 'scott-kingsley-swift', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-d3c4-4a3f-87e3-bae101d58b8d', 'Scott Kingsley Swift is family of Taylor Swift', 'scott-kingsley-swift-is-family-of-taylor-swift', 'connection', false, NULL, NULL, 1989, 12, 13, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-e40a-48b0-8fa7-0b479b413c4c', 'Big Machine Records', 'big-machine-records', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Singer-songwriter"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-e5d0-4022-9fdd-8824bd485dfa', 'Taylor Swift worked at Big Machine Records', 'taylor-swift-worked-at-big-machine-records', 'connection', false, NULL, NULL, 2006, NULL, NULL, 2018, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Singer-songwriter", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-f185-4037-b613-9f3fe9d6f942', 'Republic Records', 'republic-records', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Singer-songwriter"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-f2cd-43b7-9335-f36a6131b416', 'Taylor Swift worked at Republic Records', 'taylor-swift-worked-at-republic-records', 'connection', false, NULL, NULL, 2019, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Singer-songwriter", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-fcbd-40da-8b94-5f47a8d3c532', 'Reading, Pennsylvania, USA', 'reading-pennsylvania-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a34f-fe4c-43af-88dd-24c5f8863c6b', 'Taylor Swift lived in Reading, Pennsylvania, USA', 'taylor-swift-lived-in-reading-pennsylvania-usa', 'connection', false, NULL, NULL, 1989, NULL, NULL, 2004, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a350-0728-46d5-abeb-55fa96fb162e', 'Hendersonville, Tennessee, USA', 'hendersonville-tennessee-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a350-0886-4f77-8128-ed527a420a8b', 'Taylor Swift lived in Hendersonville, Tennessee, USA', 'taylor-swift-lived-in-hendersonville-tennessee-usa', 'connection', false, NULL, NULL, 2004, NULL, NULL, 2010, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a350-10c7-496a-b312-9d9580a3f278', 'Nashville, Tennessee, USA', 'nashville-tennessee-usa', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a350-120a-4dc1-b474-222bad4b9004', 'Taylor Swift lived in Nashville, Tennessee, USA', 'taylor-swift-lived-in-nashville-tennessee-usa', 'connection', false, NULL, NULL, 2010, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a350-19cb-451d-a7a4-adafbe4760ef', 'Joe Alwyn', 'joe-alwyn', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a350-1af2-40cb-ade9-c0ab93955e19', 'Taylor Swift has relationship with Joe Alwyn', 'taylor-swift-has-relationship-with-joe-alwyn', 'connection', false, NULL, NULL, 2016, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:47', '2025-03-26 17:43:47', NULL);
INSERT INTO public.spans VALUES ('9e86a351-002d-48a4-abd1-eac80fdf271d', 'John Lennon was member of The Beatles', 'john-lennon-was-member-of-the-beatles', 'connection', false, NULL, NULL, 1960, 8, 17, 1970, 4, 10, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Lead vocals, rhythm guitar", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a351-1f6d-4f5d-8523-947cfcb444c8', 'Paul McCartney was member of The Beatles', 'paul-mccartney-was-member-of-the-beatles', 'connection', false, NULL, NULL, 1960, 8, 17, 1970, 4, 10, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Lead vocals, bass guitar", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a351-31e3-4259-8947-172f9704a4ae', 'George Harrison was member of The Beatles', 'george-harrison-was-member-of-the-beatles', 'connection', false, NULL, NULL, 1960, 8, 17, 1970, 4, 10, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Lead guitar, vocals", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a351-3e9e-4f4f-9540-594651c712b7', 'Ringo Starr was member of The Beatles', 'ringo-starr-was-member-of-the-beatles', 'connection', false, NULL, NULL, 1962, 8, 18, 1970, 4, 10, 'day', 'day', 'placeholder', NULL, NULL, '{"role": "Drums, vocals", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a352-195d-4023-8309-9f2e345981dd', 'The Jimi Hendrix Experience', 'the-jimi-hendrix-experience-2', 'band', false, NULL, NULL, 1966, 1, 1, 1970, 1, 1, 'day', 'day', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a352-1b8a-4f70-ac68-440679a8c352', 'Jimi Hendrix was member of The Jimi Hendrix Experience', 'jimi-hendrix-was-member-of-the-jimi-hendrix-experience', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead vocals, guitar", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a352-2e03-4ff3-8ee7-96d698bdd7ec', 'Noel Redding', 'noel-redding', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Bass, backing vocals"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a352-2f49-4849-8ea8-1a7754a2c57a', 'Noel Redding was member of The Jimi Hendrix Experience', 'noel-redding-was-member-of-the-jimi-hendrix-experience', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1969, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Bass, backing vocals", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a352-388b-4e1d-acd7-622897bbb606', 'Mitch Mitchell', 'mitch-mitchell', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Drums"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a352-3a00-463d-b869-091fb3915ec2', 'Mitch Mitchell was member of The Jimi Hendrix Experience', 'mitch-mitchell-was-member-of-the-jimi-hendrix-experience', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1970, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Drums", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:48', '2025-03-26 17:43:48', NULL);
INSERT INTO public.spans VALUES ('9e86a353-1d9a-4569-9eb1-8b3f2a06a5b4', 'The Magnetic Fields', 'the-magnetic-fields-2', 'band', false, NULL, NULL, 1989, 1, 1, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-22ca-40c2-9a61-3be860aae1a2', 'Stephin Merritt was member of The Magnetic Fields', 'stephin-merritt-was-member-of-the-magnetic-fields', 'connection', false, NULL, NULL, 1989, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "lead vocals, various instruments", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-40fe-4cbe-af7e-eb567eeca2e4', 'Claudia Gonson', 'claudia-gonson', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "vocals, various instruments"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-44d3-494b-b3ee-7bf9f47a91c1', 'Claudia Gonson was member of The Magnetic Fields', 'claudia-gonson-was-member-of-the-magnetic-fields', 'connection', false, NULL, NULL, 1989, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "vocals, various instruments", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-5673-4a01-ad32-25ff25b8fd30', 'Sam Davol', 'sam-davol', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "cello, flute"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-5892-403a-a008-9755548fc922', 'Sam Davol was member of The Magnetic Fields', 'sam-davol-was-member-of-the-magnetic-fields', 'connection', false, NULL, NULL, 1991, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "cello, flute", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-6563-4ce0-9458-8ab9476941d6', 'John Woo', 'john-woo', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "banjo, guitar"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-66db-44b0-8d01-0e1fe85e1e5c', 'John Woo was member of The Magnetic Fields', 'john-woo-was-member-of-the-magnetic-fields', 'connection', false, NULL, NULL, 1994, NULL, NULL, 2004, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "banjo, guitar", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-71d6-4c45-845f-5672fb3c30bb', 'Shirley Simms', 'shirley-simms', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "vocals, ukulele"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-7349-4b88-9c79-f399a85c772a', 'Shirley Simms was member of The Magnetic Fields', 'shirley-simms-was-member-of-the-magnetic-fields', 'connection', false, NULL, NULL, 1999, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "vocals, ukulele", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-7d1a-4a80-a2e8-d4d22d376123', 'Johny Blood', 'johny-blood', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "tuba, horn"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a353-7e96-4ea6-a153-cc0a8532c187', 'Johny Blood was member of The Magnetic Fields', 'johny-blood-was-member-of-the-magnetic-fields', 'connection', false, NULL, NULL, 1991, NULL, NULL, 1992, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "tuba, horn", "connection_type": "membership"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:49', '2025-03-26 17:43:49', NULL);
INSERT INTO public.spans VALUES ('9e86a355-3c54-4ab2-a5a1-84b868ee969e', 'Carol Yorke', 'carol-yorke', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a355-4076-4dab-9d18-6745deb521ca', 'Carol Yorke is family of Thom Yorke', 'carol-yorke-is-family-of-thom-yorke', 'connection', false, NULL, NULL, 1968, 10, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a355-5ecc-4314-ac5c-aa6331bfde2c', 'Nigel Yorke', 'nigel-yorke', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a355-61f9-4f3f-ba01-ba4c722dbc7d', 'Nigel Yorke is family of Thom Yorke', 'nigel-yorke-is-family-of-thom-yorke', 'connection', false, NULL, NULL, 1968, 10, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a355-742a-433b-894e-60638cf953de', 'Noah Yorke', 'noah-yorke', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a355-75fe-4a26-98ea-996365152554', 'Thom Yorke is family of Noah Yorke', 'thom-yorke-is-family-of-noah-yorke', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a355-8213-4b9b-ac85-040c54c57e65', 'Agnes Yorke', 'agnes-yorke', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a355-8352-4a7c-967c-cc3a10d74006', 'Thom Yorke is family of Agnes Yorke', 'thom-yorke-is-family-of-agnes-yorke', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:50', NULL);
INSERT INTO public.spans VALUES ('9e86a355-8ecb-4c12-8f1e-ffcbfba82d9e', 'Thom Yorke studied at Abingdon School', 'thom-yorke-studied-at-abingdon-school', 'connection', false, NULL, NULL, 1982, NULL, NULL, 1987, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "course": "Fine Arts", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:50', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a355-97f0-4940-b46b-25796de393b2', 'Radiohead', 'radiohead-2', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Vocalist"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:51', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a355-9931-47aa-a7a7-0a104b73e56f', 'Thom Yorke worked at Radiohead', 'thom-yorke-worked-at-radiohead', 'connection', false, NULL, NULL, 1985, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Lead Vocalist", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:51', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a355-a1bf-45b6-987a-f62ba3aa549e', 'Wellingborough, England', 'wellingborough-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Birth and early childhood"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:51', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a355-a2df-424e-a896-960f133cf713', 'Thom Yorke lived in Wellingborough, England', 'thom-yorke-lived-in-wellingborough-england', 'connection', false, NULL, NULL, 1968, NULL, NULL, 1978, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Birth and early childhood", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:51', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a355-ab54-46f2-b324-b0e4cf43cca3', 'Abingdon, Oxfordshire, England', 'abingdon-oxfordshire-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Moved at age 10, formed Radiohead here"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:51', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a355-ac90-40c4-af11-8f46c8437f1a', 'Thom Yorke lived in Abingdon, Oxfordshire, England', 'thom-yorke-lived-in-abingdon-oxfordshire-england', 'connection', false, NULL, NULL, 1978, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"reason": "Moved at age 10, formed Radiohead here", "connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:51', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a355-b53e-4e88-ba32-d3dd35688e81', 'Rachel Owen', 'rachel-owen', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:51', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a355-b687-4c13-a820-b6098ffd1bf2', 'Thom Yorke has relationship with Rachel Owen', 'thom-yorke-has-relationship-with-rachel-owen', 'connection', false, NULL, NULL, 1995, NULL, NULL, 2016, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:51', '2025-03-26 17:43:51', NULL);
INSERT INTO public.spans VALUES ('9e86a357-ab12-429a-9758-4c44ccd788c1', 'Tony Blair', 'tony-blair', 'person', false, NULL, NULL, 1953, 5, 6, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-ae0e-4ace-9068-0ba4cd89bd1e', 'Hazel Corscadden', 'hazel-corscadden', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-b18d-4335-bf8a-c409e945be26', 'Hazel Corscadden is family of Tony Blair', 'hazel-corscadden-is-family-of-tony-blair', 'connection', false, NULL, NULL, 1953, 5, 6, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-cf6a-452b-aaec-438ad32e87e7', 'Leo Blair', 'leo-blair', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-d218-4a0b-bf76-09f5ef58dc83', 'Leo Blair is family of Tony Blair', 'leo-blair-is-family-of-tony-blair', 'connection', false, NULL, NULL, 1953, 5, 6, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-e3ed-473b-b5c0-dffea70c8de6', 'Euan Blair', 'euan-blair', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-e584-48bf-9406-c14f341075a7', 'Tony Blair is family of Euan Blair', 'tony-blair-is-family-of-euan-blair', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-f178-4e95-8f74-7c27f668c4cf', 'Nick Blair', 'nick-blair', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-f2e3-4497-8768-7f88f944f155', 'Tony Blair is family of Nick Blair', 'tony-blair-is-family-of-nick-blair', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-fd39-4c7a-a8fa-67473dc3a4ff', 'Kathryn Blair', 'kathryn-blair', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a357-fe7b-42ee-a3cb-a7b8f1902b89', 'Tony Blair is family of Kathryn Blair', 'tony-blair-is-family-of-kathryn-blair', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-07db-44ac-86c3-d9eb7e3dab1f', 'Tony Blair is family of Leo Blair', 'tony-blair-is-family-of-leo-blair', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-1064-4e5c-a8c6-f4de9ef15665', 'The Chorister School', 'the-chorister-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-11a1-427b-89dd-c180478cf9a7', 'Tony Blair studied at The Chorister School', 'tony-blair-studied-at-the-chorister-school', 'connection', false, NULL, NULL, 1961, NULL, NULL, 1966, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-1a5c-4957-b4d8-29cb8cac2306', 'Fettes College', 'fettes-college', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-1b63-4725-b150-730fcc892ce2', 'Tony Blair studied at Fettes College', 'tony-blair-studied-at-fettes-college', 'connection', false, NULL, NULL, 1966, NULL, NULL, 1971, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-23ee-4b0a-8f85-ed5c464ad0aa', 'St John’s College, Oxford', 'st-johns-college-oxford', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Jurisprudence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-2510-4989-8851-9cf7a03c1228', 'Tony Blair studied at St John’s College, Oxford', 'tony-blair-studied-at-st-johns-college-oxford', 'connection', false, NULL, NULL, 1972, NULL, NULL, 1975, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Jurisprudence", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-37ec-4e68-b84b-a3fa18080eeb', 'Tony Blair worked at Labour Party', 'tony-blair-worked-at-labour-party', 'connection', false, NULL, NULL, 1994, NULL, NULL, 2007, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Leader of the Labour Party", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-2eac-4f8f-ac27-7357fa8f108e', 'Tony Blair worked at UK Government', 'tony-blair-worked-at-uk-government', 'connection', false, NULL, NULL, 1997, NULL, NULL, 2007, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Prime Minister of the United Kingdom", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-426d-4497-b904-c241151b7b66', 'Quartet on the Middle East', 'quartet-on-the-middle-east', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Middle East envoy for the United Nations, European Union, United States, and Russia"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-4382-47c7-8462-a60c4d804285', 'Tony Blair worked at Quartet on the Middle East', 'tony-blair-worked-at-quartet-on-the-middle-east', 'connection', false, NULL, NULL, 2007, NULL, NULL, 2015, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Middle East envoy for the United Nations, European Union, United States, and Russia", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-4c6b-4d5c-8504-9ae84e193846', 'Edinburgh, Scotland', 'edinburgh-scotland', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-4da7-46ed-b529-c1d8a8128130', 'Tony Blair lived in Edinburgh, Scotland', 'tony-blair-lived-in-edinburgh-scotland', 'connection', false, NULL, NULL, 1953, NULL, NULL, 1966, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-5653-43c6-9253-a72a055cc5d7', 'Durham, England', 'durham-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-5783-42ac-bb6b-f2f21b5f0dba', 'Tony Blair lived in Durham, England', 'tony-blair-lived-in-durham-england', 'connection', false, NULL, NULL, 1961, NULL, NULL, 1966, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-610c-450a-b8d0-36d1a79dfec1', 'Tony Blair lived in Oxford, England', 'tony-blair-lived-in-oxford-england', 'connection', false, NULL, NULL, 1972, NULL, NULL, 1975, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-69ee-495c-999f-971884f7f49e', 'Sedgefield, England', 'sedgefield-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-6b2b-44d5-abb1-55639a901868', 'Tony Blair lived in Sedgefield, England', 'tony-blair-lived-in-sedgefield-england', 'connection', false, NULL, NULL, 1983, NULL, NULL, 2007, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-74ad-45f6-b340-da276587bc35', 'Tony Blair lived in London, England', 'tony-blair-lived-in-london-england', 'connection', false, NULL, NULL, 1997, NULL, NULL, 2007, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-7d8f-4df1-a22c-f0920f490226', 'Cherie Blair', 'cherie-blair', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a358-7ecb-4218-bcae-b77fca7323d0', 'Tony Blair has relationship with Cherie Blair', 'tony-blair-has-relationship-with-cherie-blair', 'connection', false, NULL, NULL, 1980, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:52', '2025-03-26 17:43:52', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-bbe7-41c8-bf22-d6c7c8515b7e', 'Vladimir Putin', 'vladimir-putin', 'person', false, NULL, NULL, 1952, 10, 7, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-be2c-483f-af99-1ab1f4421035', 'Maria Ivanovna Putina', 'maria-ivanovna-putina', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-c01b-4180-89b1-816a91b32a8b', 'Maria Ivanovna Putina is family of Vladimir Putin', 'maria-ivanovna-putina-is-family-of-vladimir-putin', 'connection', false, NULL, NULL, 1952, 10, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-d582-4e51-a8c2-f3556d4741da', 'Vladimir Spiridonovich Putin', 'vladimir-spiridonovich-putin', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-d859-4333-8211-ba05392366fa', 'Vladimir Spiridonovich Putin is family of Vladimir Putin', 'vladimir-spiridonovich-putin-is-family-of-vladimir-putin', 'connection', false, NULL, NULL, 1952, 10, 7, NULL, NULL, NULL, 'day', 'year', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-e389-4eef-9bbd-b4e086cbcdf9', 'Maria Vorontsova', 'maria-vorontsova', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-e4e5-456f-96a6-72140158761b', 'Vladimir Putin is family of Maria Vorontsova', 'vladimir-putin-is-family-of-maria-vorontsova', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-ef73-4e6a-be14-69caf5f79afe', 'Katerina Tikhonova', 'katerina-tikhonova', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-f0ba-42c6-97da-64fee95a81a9', 'Vladimir Putin is family of Katerina Tikhonova', 'vladimir-putin-is-family-of-katerina-tikhonova', 'connection', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-fb51-4000-9e42-6d832cff8aa3', 'Leningrad State University', 'leningrad-state-university', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Law"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35c-fc7d-4336-8983-8623508ec8a9', 'Vladimir Putin studied at Leningrad State University', 'vladimir-putin-studied-at-leningrad-state-university', 'connection', false, NULL, NULL, 1970, NULL, NULL, 1975, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "undergraduate", "course": "Law", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35d-086a-4fe7-9e3a-6359fb360e9a', 'Vladimir Putin worked at Government of Russia', 'vladimir-putin-worked-at-government-of-russia', 'connection', false, NULL, NULL, 1997, NULL, NULL, 1998, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Deputy Chief of Presidential Staff", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35d-17bf-45c5-916e-eec34cf079e8', 'Leningrad, Soviet Union', 'leningrad-soviet-union', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35d-1906-472a-8dcd-22a2ed16fbc6', 'Vladimir Putin lived in Leningrad, Soviet Union', 'vladimir-putin-lived-in-leningrad-soviet-union', 'connection', false, NULL, NULL, 1952, NULL, NULL, 1975, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35d-231b-4a29-a94d-7b55d11503fd', 'Moscow, Russia', 'moscow-russia', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35d-245e-4c18-99d8-64062079d614', 'Vladimir Putin lived in Moscow, Russia', 'vladimir-putin-lived-in-moscow-russia', 'connection', false, NULL, NULL, 2000, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:55', '2025-03-26 17:43:55', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-084e-44c5-899f-64bb227f9014', 'Winston Churchill', 'winston-churchill', 'person', false, NULL, NULL, 1874, 11, 30, 1965, 1, 24, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-0c2d-4ae2-87d1-5ab19b37471f', 'Jennie Jerome', 'jennie-jerome', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "female"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-0fa9-4c8d-a2d8-bf056f875a57', 'Jennie Jerome is family of Winston Churchill', 'jennie-jerome-is-family-of-winston-churchill', 'connection', false, NULL, NULL, 1874, 11, 30, 1965, 1, 24, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "mother", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-2ff7-40d9-8210-2ea88e88898d', 'Lord Randolph Churchill', 'lord-randolph-churchill', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"gender": "male"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-3351-4b88-b8c0-a1775ad5b56e', 'Lord Randolph Churchill is family of Winston Churchill', 'lord-randolph-churchill-is-family-of-winston-churchill', 'connection', false, NULL, NULL, 1874, 11, 30, 1965, 1, 24, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "father", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-45b3-47c4-9f9d-2afed3c048b7', 'Diana Churchill', 'diana-churchill', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-4753-4be4-852d-569f83e205d9', 'Winston Churchill is family of Diana Churchill', 'winston-churchill-is-family-of-diana-churchill', 'connection', false, NULL, NULL, NULL, NULL, NULL, 1965, 1, 24, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-542d-4b4f-bdeb-2940c9ab7827', 'Randolph Churchill', 'randolph-churchill', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-5563-4967-9c4a-20d3152687ce', 'Winston Churchill is family of Randolph Churchill', 'winston-churchill-is-family-of-randolph-churchill', 'connection', false, NULL, NULL, NULL, NULL, NULL, 1965, 1, 24, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-6019-4360-a6b9-fc90ac186820', 'Sarah Churchill', 'sarah-churchill', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-614d-40c6-bc0f-5ba5e590ebb2', 'Winston Churchill is family of Sarah Churchill', 'winston-churchill-is-family-of-sarah-churchill', 'connection', false, NULL, NULL, NULL, NULL, NULL, 1965, 1, 24, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-6aca-46d8-b29e-cebe52f10832', 'Marigold Churchill', 'marigold-churchill', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-6bd5-4258-82fe-217a212153ff', 'Winston Churchill is family of Marigold Churchill', 'winston-churchill-is-family-of-marigold-churchill', 'connection', false, NULL, NULL, NULL, NULL, NULL, 1965, 1, 24, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-76b7-4c47-a390-b54c09d0dbca', 'Winston Churchill is family of Mary Soames, Baroness Soames', 'winston-churchill-is-family-of-mary-soames-baroness-soames', 'connection', false, NULL, NULL, NULL, NULL, NULL, 1965, 1, 24, 'year', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-8052-4dce-807e-fde438e4b9f5', 'St. George''s School, Ascot', 'st-georges-school-ascot', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-819b-473d-910f-e027db318552', 'Winston Churchill studied at St. George''s School, Ascot', 'winston-churchill-studied-at-st-georges-school-ascot', 'connection', false, NULL, NULL, 1882, NULL, NULL, 1884, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "primary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-8ad4-4125-a51f-73ad9b216705', 'Harrow School', 'harrow-school', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-8bda-437f-99cf-951541be67c6', 'Winston Churchill studied at Harrow School', 'winston-churchill-studied-at-harrow-school', 'connection', false, NULL, NULL, 1888, NULL, NULL, 1893, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "secondary", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-954f-49dd-bd69-a93798ebfbd9', 'Royal Military Academy, Sandhurst', 'royal-military-academy-sandhurst', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "tertiary", "course": "Military Training"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-967c-4474-83c0-08ac0e6bc2d1', 'Winston Churchill studied at Royal Military Academy, Sandhurst', 'winston-churchill-studied-at-royal-military-academy-sandhurst', 'connection', false, NULL, NULL, 1893, NULL, NULL, 1894, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"level": "tertiary", "course": "Military Training", "connection_type": "education"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-a068-4913-94ac-81dec835e433', 'British Army', 'british-army', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Army Officer"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-a1bd-463b-be95-1b62844c1b1c', 'Winston Churchill worked at British Army', 'winston-churchill-worked-at-british-army', 'connection', false, NULL, NULL, 1895, NULL, NULL, 1899, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Army Officer", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-ab4c-440e-a4d3-dc0e7ef8f32e', 'Various Publications', 'various-publications', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Journalist and War Correspondent"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-ac57-483d-bdd0-158fa46dd965', 'Winston Churchill worked at Various Publications', 'winston-churchill-worked-at-various-publications', 'connection', false, NULL, NULL, 1899, NULL, NULL, 1900, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Journalist and War Correspondent", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-b5f7-4333-93c6-6fadef6d49a3', 'British Government', 'british-government', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Member of Parliament (MP)"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:56', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-b70e-4dab-87ee-cd648208ee62', 'Winston Churchill worked at British Government', 'winston-churchill-worked-at-british-government', 'connection', false, NULL, NULL, 1911, NULL, NULL, 1915, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "First Lord of the Admiralty", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:56', '2025-03-26 17:43:57', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-c1d9-4830-b273-c430672cb72c', 'United Kingdom', 'united-kingdom', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Prime Minister"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:57', '2025-03-26 17:43:57', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-c2ed-4e51-9ed1-c7e91a0be6b6', 'Winston Churchill worked at United Kingdom', 'winston-churchill-worked-at-united-kingdom', 'connection', false, NULL, NULL, 1951, NULL, NULL, 1955, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"role": "Prime Minister", "connection_type": "employment"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:57', '2025-03-26 17:43:57', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-cd87-434b-b11a-25ceff4786a7', 'Blenheim Palace, Oxfordshire, England', 'blenheim-palace-oxfordshire-england', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:57', '2025-03-26 17:43:57', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-cebb-4ec3-9a29-d2e2aa2caac3', 'Winston Churchill lived in Blenheim Palace, Oxfordshire, England', 'winston-churchill-lived-in-blenheim-palace-oxfordshire-england', 'connection', false, NULL, NULL, 1874, NULL, NULL, 1882, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:57', '2025-03-26 17:43:57', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-d8ea-4395-8457-26bb427363c2', 'Winston Churchill lived in London, England', 'winston-churchill-lived-in-london-england', 'connection', false, NULL, NULL, 1882, NULL, NULL, 1965, 1, 24, 'year', 'day', 'placeholder', NULL, NULL, '{"connection_type": "residence"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:57', '2025-03-26 17:43:57', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-e41a-4d68-9173-c5f88de70ce8', 'Clementine Hozier', 'clementine-hozier', 'person', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '[]', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:57', '2025-03-26 17:43:57', NULL);
INSERT INTO public.spans VALUES ('9e86a35e-e54a-4873-8401-86bc6ae84708', 'Winston Churchill has relationship with Clementine Hozier', 'winston-churchill-has-relationship-with-clementine-hozier', 'connection', false, NULL, NULL, 1908, NULL, NULL, 1965, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"connection_type": "relationship"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:57', '2025-03-26 17:43:57', NULL);
INSERT INTO public.spans VALUES ('9e86a300-e01a-4af3-9cc8-c27d29478406', 'Ulm, Kingdom of Württemberg', 'ulm-kingdom-of-wurttemberg-german-empire', 'place', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"country": null, "subtype": null, "coordinates": null}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:55', '2025-03-31 15:45:52', NULL);
INSERT INTO public.spans VALUES ('9e86a2ff-2141-4020-a294-d445d5bb9aca', 'St Michael''s, Hastings', 'st-michaels-a-preparatory-school-in-the-seaside-town-of-st-leonards-on-sea-hastings-england', 'organisation', false, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'year', 'year', 'placeholder', NULL, NULL, '{"size": null, "subtype": null, "industry": null}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:42:54', '2025-03-31 15:48:28', NULL);
INSERT INTO public.spans VALUES ('9e86a30c-8627-44ed-9d04-21975f8797d6', 'Joseph Northover', 'joseph-northover', 'person', false, NULL, NULL, 1919, 5, 5, 1993, 5, 20, 'day', 'day', 'complete', NULL, NULL, '{"gender": "male", "birth_name": null, "occupation": null, "nationality": null}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:03', '2025-03-31 15:55:37', NULL);
INSERT INTO public.spans VALUES ('9e86a30c-893d-4faa-87bb-646021108ae2', 'Joseph Northover is family of Chris Northover', 'joseph-northover-is-family-of-chris-northover', 'connection', false, NULL, NULL, 1951, 2, 2, 1993, 5, 20, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:03', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a328-0273-48d5-b1be-66ebb2881819', 'Joseph Northover is family of John Northover', 'joseph-northover-is-family-of-john-northover', 'connection', false, NULL, NULL, 1947, 6, 16, 1993, 5, 20, 'day', 'day', 'placeholder', NULL, NULL, '{"relationship": "child", "connection_type": "family"}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:21', '2025-03-26 17:43:22', NULL);
INSERT INTO public.spans VALUES ('9e86a309-31fc-4284-8600-d3ea311a89dc', 'Chris Northover', 'chris-northover', 'person', false, NULL, NULL, 1951, 2, 2, NULL, NULL, NULL, 'day', 'year', 'complete', NULL, NULL, '{"gender": "male", "birth_name": null, "occupation": null, "nationality": null}', NULL, 420, 'own', 'private', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '2025-03-26 17:43:00', '2025-03-31 16:05:27', NULL);


--
-- Data for Name: spatial_ref_sys; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--



--
-- Data for Name: telescope_entries; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--



--
-- Data for Name: telescope_entries_tags; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--



--
-- Data for Name: telescope_monitoring; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--



--
-- Data for Name: user_spans; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--

INSERT INTO public.user_spans VALUES ('7e2e022d-edbf-4c09-9c82-bbcc722ebe46', 'd599225a-a3d3-41e9-a4eb-955bbc5ed446', '3944dfe6-8368-4934-b5ad-ffc81894a362', 'owner', '2025-03-26 17:42:27', '2025-03-26 17:42:27');
INSERT INTO public.user_spans VALUES ('5e562b4f-d84e-496b-9e90-4368a02f19e6', '9e86a2d6-2f45-4f74-9c75-be9c662c2b98', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', 'owner', '2025-03-26 17:42:27', '2025-03-26 17:42:27');


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: lifespan_user
--

INSERT INTO public.users VALUES ('d599225a-a3d3-41e9-a4eb-955bbc5ed446', 'system@example.com', '2025-03-26 17:42:27', '$2y$12$kvjMVNi/2JcPb2c2w8UM0uzBanl4Tume9wycLOGu7X2mzbsIsRr0i', NULL, '2025-03-26 17:42:27', '2025-03-26 17:42:27', '3944dfe6-8368-4934-b5ad-ffc81894a362', false, NULL);
INSERT INTO public.users VALUES ('9e86a2d6-2f45-4f74-9c75-be9c662c2b98', 'richard@northover.info', '2025-03-26 17:42:27', '$2y$12$0ITHy8SHhce4nkjxA6fvs..EIebWNkVspNizzkT6uPASB8oYMn.0m', NULL, '2025-03-26 17:42:27', '2025-03-26 17:42:27', '9e86a2d6-372a-4c5f-82bb-f4d8ccc88047', true, NULL);


--
-- Name: invitation_codes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: lifespan_user
--

SELECT pg_catalog.setval('public.invitation_codes_id_seq', 18, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: lifespan_user
--

SELECT pg_catalog.setval('public.migrations_id_seq', 28, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: lifespan_user
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: telescope_entries_sequence_seq; Type: SEQUENCE SET; Schema: public; Owner: lifespan_user
--

SELECT pg_catalog.setval('public.telescope_entries_sequence_seq', 1, false);


--
-- Name: connection_types connection_types_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.connection_types
    ADD CONSTRAINT connection_types_pkey PRIMARY KEY (type);


--
-- Name: connections connections_connection_span_id_unique; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_connection_span_id_unique UNIQUE (connection_span_id);


--
-- Name: connections connections_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_pkey PRIMARY KEY (id);


--
-- Name: invitation_codes invitation_codes_code_unique; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.invitation_codes
    ADD CONSTRAINT invitation_codes_code_unique UNIQUE (code);


--
-- Name: invitation_codes invitation_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.invitation_codes
    ADD CONSTRAINT invitation_codes_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: span_permissions span_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.span_permissions
    ADD CONSTRAINT span_permissions_pkey PRIMARY KEY (id);


--
-- Name: span_permissions span_permissions_span_id_user_id_group_id_permission_type_uniqu; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.span_permissions
    ADD CONSTRAINT span_permissions_span_id_user_id_group_id_permission_type_uniqu UNIQUE (span_id, user_id, group_id, permission_type);


--
-- Name: span_types span_types_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.span_types
    ADD CONSTRAINT span_types_pkey PRIMARY KEY (type_id);


--
-- Name: spans spans_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.spans
    ADD CONSTRAINT spans_pkey PRIMARY KEY (id);


--
-- Name: spans spans_slug_unique; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.spans
    ADD CONSTRAINT spans_slug_unique UNIQUE (slug);


--
-- Name: telescope_entries telescope_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.telescope_entries
    ADD CONSTRAINT telescope_entries_pkey PRIMARY KEY (sequence);


--
-- Name: telescope_entries_tags telescope_entries_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.telescope_entries_tags
    ADD CONSTRAINT telescope_entries_tags_pkey PRIMARY KEY (entry_uuid, tag);


--
-- Name: telescope_entries telescope_entries_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.telescope_entries
    ADD CONSTRAINT telescope_entries_uuid_unique UNIQUE (uuid);


--
-- Name: telescope_monitoring telescope_monitoring_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.telescope_monitoring
    ADD CONSTRAINT telescope_monitoring_pkey PRIMARY KEY (tag);


--
-- Name: user_spans user_spans_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.user_spans
    ADD CONSTRAINT user_spans_pkey PRIMARY KEY (id);


--
-- Name: user_spans user_spans_user_id_span_id_unique; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.user_spans
    ADD CONSTRAINT user_spans_user_id_span_id_unique UNIQUE (user_id, span_id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_personal_span_id_unique; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_personal_span_id_unique UNIQUE (personal_span_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: connections_child_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX connections_child_id_index ON public.connections USING btree (child_id);


--
-- Name: connections_connection_span_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX connections_connection_span_id_index ON public.connections USING btree (connection_span_id);


--
-- Name: connections_parent_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX connections_parent_id_index ON public.connections USING btree (parent_id);


--
-- Name: connections_type_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX connections_type_id_index ON public.connections USING btree (type_id);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: span_connections_span_id_idx; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE UNIQUE INDEX span_connections_span_id_idx ON public.span_connections USING btree (span_id);


--
-- Name: span_permissions_span_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX span_permissions_span_id_index ON public.span_permissions USING btree (span_id);


--
-- Name: span_permissions_user_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX span_permissions_user_id_index ON public.span_permissions USING btree (user_id);


--
-- Name: spans_end_date_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_end_date_index ON public.spans USING btree (end_year, end_month, end_day);


--
-- Name: spans_owner_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_owner_id_index ON public.spans USING btree (owner_id);


--
-- Name: spans_parent_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_parent_id_index ON public.spans USING btree (parent_id);


--
-- Name: spans_permission_mode_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_permission_mode_index ON public.spans USING btree (permission_mode);


--
-- Name: spans_precision_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_precision_index ON public.spans USING btree (start_precision, end_precision);


--
-- Name: spans_root_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_root_id_index ON public.spans USING btree (root_id);


--
-- Name: spans_start_date_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_start_date_index ON public.spans USING btree (start_year, start_month, start_day);


--
-- Name: spans_start_year_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_start_year_index ON public.spans USING btree (start_year);


--
-- Name: spans_type_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_type_id_index ON public.spans USING btree (type_id);


--
-- Name: spans_updater_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX spans_updater_id_index ON public.spans USING btree (updater_id);


--
-- Name: telescope_entries_batch_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX telescope_entries_batch_id_index ON public.telescope_entries USING btree (batch_id);


--
-- Name: telescope_entries_created_at_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX telescope_entries_created_at_index ON public.telescope_entries USING btree (created_at);


--
-- Name: telescope_entries_family_hash_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX telescope_entries_family_hash_index ON public.telescope_entries USING btree (family_hash);


--
-- Name: telescope_entries_tags_tag_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX telescope_entries_tags_tag_index ON public.telescope_entries_tags USING btree (tag);


--
-- Name: telescope_entries_type_should_display_on_index_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX telescope_entries_type_should_display_on_index_index ON public.telescope_entries USING btree (type, should_display_on_index);


--
-- Name: user_spans_access_level_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX user_spans_access_level_index ON public.user_spans USING btree (access_level);


--
-- Name: user_spans_span_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX user_spans_span_id_index ON public.user_spans USING btree (span_id);


--
-- Name: user_spans_user_id_index; Type: INDEX; Schema: public; Owner: lifespan_user
--

CREATE INDEX user_spans_user_id_index ON public.user_spans USING btree (user_id);


--
-- Name: connections connection_sync_trigger; Type: TRIGGER; Schema: public; Owner: lifespan_user
--

CREATE TRIGGER connection_sync_trigger AFTER INSERT OR DELETE OR UPDATE ON public.connections FOR EACH ROW EXECUTE FUNCTION public.sync_span_connections();


--
-- Name: connections enforce_temporal_constraint; Type: TRIGGER; Schema: public; Owner: lifespan_user
--

CREATE TRIGGER enforce_temporal_constraint BEFORE INSERT OR UPDATE ON public.connections FOR EACH ROW EXECUTE FUNCTION public.check_temporal_constraint();


--
-- Name: spans update_family_connection_dates; Type: TRIGGER; Schema: public; Owner: lifespan_user
--

CREATE TRIGGER update_family_connection_dates AFTER UPDATE ON public.spans FOR EACH ROW EXECUTE FUNCTION public.update_family_connection_dates();


--
-- Name: connections connections_child_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_child_id_foreign FOREIGN KEY (child_id) REFERENCES public.spans(id) ON DELETE CASCADE;


--
-- Name: connections connections_connection_span_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_connection_span_id_foreign FOREIGN KEY (connection_span_id) REFERENCES public.spans(id) ON DELETE CASCADE;


--
-- Name: connections connections_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.spans(id) ON DELETE CASCADE;


--
-- Name: connections connections_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.connections
    ADD CONSTRAINT connections_type_id_foreign FOREIGN KEY (type_id) REFERENCES public.connection_types(type) ON DELETE CASCADE;


--
-- Name: span_permissions span_permissions_span_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.span_permissions
    ADD CONSTRAINT span_permissions_span_id_foreign FOREIGN KEY (span_id) REFERENCES public.spans(id) ON DELETE CASCADE;


--
-- Name: span_permissions span_permissions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.span_permissions
    ADD CONSTRAINT span_permissions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: spans spans_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.spans
    ADD CONSTRAINT spans_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id);


--
-- Name: spans spans_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.spans
    ADD CONSTRAINT spans_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.spans(id) ON DELETE SET NULL;


--
-- Name: spans spans_root_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.spans
    ADD CONSTRAINT spans_root_id_foreign FOREIGN KEY (root_id) REFERENCES public.spans(id) ON DELETE SET NULL;


--
-- Name: spans spans_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.spans
    ADD CONSTRAINT spans_type_id_foreign FOREIGN KEY (type_id) REFERENCES public.span_types(type_id);


--
-- Name: spans spans_updater_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.spans
    ADD CONSTRAINT spans_updater_id_foreign FOREIGN KEY (updater_id) REFERENCES public.users(id);


--
-- Name: telescope_entries_tags telescope_entries_tags_entry_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.telescope_entries_tags
    ADD CONSTRAINT telescope_entries_tags_entry_uuid_foreign FOREIGN KEY (entry_uuid) REFERENCES public.telescope_entries(uuid) ON DELETE CASCADE;


--
-- Name: user_spans user_spans_span_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.user_spans
    ADD CONSTRAINT user_spans_span_id_foreign FOREIGN KEY (span_id) REFERENCES public.spans(id) ON DELETE CASCADE;


--
-- Name: user_spans user_spans_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.user_spans
    ADD CONSTRAINT user_spans_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_personal_span_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: lifespan_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_personal_span_id_foreign FOREIGN KEY (personal_span_id) REFERENCES public.spans(id);


--
-- Name: span_connections; Type: MATERIALIZED VIEW DATA; Schema: public; Owner: lifespan_user
--

REFRESH MATERIALIZED VIEW public.span_connections;


--
-- PostgreSQL database dump complete
--

