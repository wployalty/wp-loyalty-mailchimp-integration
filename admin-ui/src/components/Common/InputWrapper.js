import React from 'react';
import Input from "./Input";
import {responsive} from "../../helpers/utilities";

const InputWrapper = ({
                          label, width = "w-1/2", value, onChange, onkeydown, type,
                          border = "border border-card_border  focus:border-primary  focus:border-1  focus:border-opacity-100",
                          error_message, error
                      }) => {
    return <div className={`flex flex-col gap-y-1 ${width}`}>
        <p className={`text-light text-xs 2xl:text-sm font-semibold tracking-wide`}>{label}</p>
        <div
            className={`border border-light_border ${error && "wll_input-error"}  px-2 rounded-md bg-white flex items-center space-between gap-x-3  `}>
            <Input
                type={type}
                value={value}
                onChange={onChange}
                onkeyDown={onkeydown}
                border={border}
            />
            <p className={`text-dark font-normal ${responsive.text.sm}`}>px</p>
        </div>
        {error_message && <div className="flex items-center space-x-1">
            <i className="text-md  antialiased wlr wlrf-error font-semibold text-redd color-important "/>
            <p className="text-redd font-semibold text-xs  tracking-wide">{error_message}</p>
        </div>
        }
    </div>
};

export default InputWrapper;