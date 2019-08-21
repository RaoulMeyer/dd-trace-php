#include "trace.h"

#include <Zend/zend_exceptions.h>
#include <php.h>

#include "dispatch.h"
#include "dispatch_compat.h"
#include "span.h"

/* Move these to a header if dispatch.c still needs it */
#if PHP_VERSION_ID >= 70100
#define RETURN_VALUE_USED(opline) ((opline)->result_type != IS_UNUSED)
#else
#define RETURN_VALUE_USED(opline) (!((opline)->result_type & EXT_TYPE_UNUSED))
#endif

/* Why did we redef this? */
#if PHP_VERSION_ID < 70000
#undef EX
#define EX(x) ((execute_data)->x)
#endif

void ddtrace_trace_dispatch(ddtrace_dispatch_t *dispatch, zend_function *fbc,
                            zend_execute_data *execute_data TSRMLS_DC) {
    int fcall_status;
    const zend_op *opline = EX(opline);

    zval *this = ddtrace_this(execute_data);
    zval *user_retval = NULL;
#if PHP_VERSION_ID < 70000
    ALLOC_INIT_ZVAL(user_retval);
#else
    zval rv;
    INIT_ZVAL(rv);
    user_retval = (RETURN_VALUE_USED(opline) ? EX_VAR(opline->result.var) : &rv);
#endif

    ddtrace_span_stack_t *stack = ddtrace_open_span(TSRMLS_C);
#if PHP_VERSION_ID < 70000
    fcall_status = ddtrace_forward_call(execute_data, fbc, user_retval TSRMLS_CC);
#else
    zend_fcall_info fci = {0};
    zend_fcall_info_cache fcc = {0};
    fcall_status = ddtrace_forward_call(EX(call), fbc, user_retval, &fci, &fcc TSRMLS_CC);
#endif
    // TODO Add dd_trace_stop_span_time() to stop the timer
    ddtrace_close_span(TSRMLS_C);

    if (fcall_status == SUCCESS && !EG(exception) && Z_TYPE(dispatch->callable) == IS_OBJECT) {
        int orig_error_reporting = EG(error_reporting);
        EG(error_reporting) = 0;
        ddtrace_execute_tracing_closure(&dispatch->callable, stack->span_data, execute_data, user_retval TSRMLS_CC);
        EG(error_reporting) = orig_error_reporting;
        // If the tracing closure threw an exception, ignore it to not impact the original call
        if (EG(exception)) {
            // TODO Log the exception
            zend_clear_exception(TSRMLS_C);
        }
    }

#if PHP_VERSION_ID < 50500
    if (user_retval != NULL) {
        if (RETURN_VALUE_USED(opline)) {
            zval **return_value_ptr = &(*(temp_variable *)((char *) execute_data->Ts + execute_data->opline->result.var)).var.ptr;
            *return_value_ptr = user_retval;
        } else {
            zval_dtor(user_retval);
            efree(user_retval);
        }
    }
#elif PHP_VERSION_ID < 70000
    // Put the original return value on the opline
    if (user_retval != NULL) {
        if (RETURN_VALUE_USED(opline)) {
            EX_TMP_VAR(execute_data, opline->result.var)->var.ptr = user_retval;
        } else {
            zval_ptr_dtor(&user_retval);
        }
    }
#else
    zend_fcall_info_args_clear(&fci, 0);
    if (!RETURN_VALUE_USED(opline)) {
        zval_dtor(&rv);
    }
#endif

#if PHP_VERSION_ID < 70000
    if (this) {
        Z_DELREF_P(this);
    }
#else
    if (this) {  // May need to check: EX_CALL_INFO() & ZEND_CALL_RELEASE_THIS
        OBJ_RELEASE(Z_OBJ_P(this));
    }
#endif

#if PHP_VERSION_ID < 50500
    // Free any remaining args
    zend_vm_stack_clear_multiple(TSRMLS_C);
#elif PHP_VERSION_ID < 70000
    // Free any remaining args
    zend_vm_stack_clear_multiple(0 TSRMLS_CC);
    // Restore call for internal functions
    EX(call)--;
#else
    // Restore call for internal functions
    EX(call) = EX(call)->prev_execute_data;
#endif
}
