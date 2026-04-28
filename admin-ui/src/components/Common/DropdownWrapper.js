import React from 'react';
import Icon from "./Icon";

const DropdownWrapper = ({options, value, handleDropDownClick, label, width = "wlmi-w-full"}) => {
    const [show, setShow] = React.useState(false);
    return <div
        onClick={() => setShow(!show)}
        className={`wlmi-border wlmi-border-card_border wlmi-relative wlmi-rounded-md wlmi-flex wlmi-items-center wlmi-h-11 wlmi-justify-between 2xl:wlmi-p-2 wlmi-p-1.5 ${width} wlmi-cursor-pointer`}>
        <p className={`wlmi-text-dark wlmi-text-xs 2xl:wlmi-text-sm wlmi-font-medium wlmi-tracking-wide`}>{label}</p>
        <Icon icon={"arrow-down"} color={"wlmi-text-dark"}
        />
        {show && <div
            className={`wlmi-flex wlmi-flex-col wlmi-border wlmi-rounded-lg wlmi-bg-white wlmi-w-full wlmi-text-light wlmi-border-light_border wlmi-z-10 wlmi-absolute wlmi-top-11.5 wlmi-left-0 wlmi-overflow-hidden`}>
            {
                options.map((item, index) => {
                    return <p
                        key={index}
                        onClick={() => handleDropDownClick(item)}
                        className={`wlmi-flex wlmi-items-center  wlmi-px-4 wlmi-py-2 wlmi-justify-between 
                                            ${item.value === value ? "wlmi-bg-primary_extra_light wlmi-text-primary" : "wlmi-bg-white wlmi-text-dark "} 
                                            hover:wlmi-bg-primary_extra_light wlmi-cursor-pointer hover:wlmi-bg-opacity-50`}
                    >
                        {item.label}
                        {item.value === value &&
                            <span className='wlmi-flex wlmi-items-center'>
                                                <i
                                                    className=" wlr wlrf-tick color-important wlmi-font-medium  wlmi-text-lg 2xl:wlmi-text-xl leading-0 wlmi-cursor-pointer "
                                                />
                                                 </span>
                        }
                    </p>
                })
            }
        </div>

        }
    </div>
};

export default DropdownWrapper;