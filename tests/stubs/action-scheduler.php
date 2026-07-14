<?php

declare(strict_types=1);

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '', $unique = false, $unique_key = '' ) {
		unset( $group, $unique, $unique_key );
		if ( empty( $GLOBALS['pks_oi_test_as_defer_sync'] ) && (int) $timestamp <= time() ) {
			\PrikOgStreg\OnlineInvitations\Scheduling\ActionSchedulerBridge::run_sync( (string) $hook, is_array( $args ) ? $args : [] );
		}

		return 1;
	}
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	function as_next_scheduled_action( $hook, $args = null, $group = '' ) {
		unset( $hook, $args, $group );

		return false;
	}
}

if ( ! function_exists( 'as_unschedule_action' ) ) {
	function as_unschedule_action( $hook, $args = array(), $group = '' ) {
		unset( $hook, $args, $group );

		return true;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) {
		unset( $hook, $args, $group );

		return true;
	}
}
